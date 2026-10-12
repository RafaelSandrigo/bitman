<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure;

use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\Exceptions\ArtifactAlreadyExists;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * Volume `artifacts`. Funções nativas de propósito: criação exclusiva (`mkdir`, `fopen 'x'`) para
 * o write-once (I7) e `rename` atômico para o `served/` (J6): a origem de jogos nunca vê um
 * diretório pela metade. O worker do nginx `bitman-games` lê com 0755/0644.
 */
final readonly class FilesystemArtifactStore implements ArtifactStore
{
    private const ULID = '/^[0-9a-hjkmnp-tv-z]{26}$/D';

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    /** @param  list<string>  $allowedPaths  a allowlist da política (os únicos nomes de arquivo possíveis) */
    public function __construct(
        private string $root,
        private array $allowedPaths,
    ) {}

    public function archive(string $gameId, int $number, array $files): void
    {
        $dir = $this->archiveDir($gameId, $number);
        $this->ensureDir(dirname($dir), 0750);
        if (! @mkdir($dir, 0750)) {
            throw file_exists($dir) ? new ArtifactAlreadyExists("archive {$gameId} v{$number}") : new RuntimeException("Não foi possível criar {$dir}.");
        }

        foreach ($files as $path => $contents) {
            $this->writeNew($dir.'/'.$this->allowed($path), $contents, 0640);
        }
    }

    public function readArchive(string $gameId, int $number, array $paths): array
    {
        $dir = $this->archiveDir($gameId, $number);
        $files = [];
        foreach ($paths as $path) {
            $contents = @file_get_contents($dir.'/'.$this->allowed($path));
            if ($contents === false) {
                throw new RuntimeException("Arquivo permanente ausente: {$gameId} v{$number} {$path}.");
            }
            $files[$path] = $contents;
        }

        return $files;
    }

    public function discardUncommittedArchive(string $gameId, int $number): void
    {
        $this->removeTree($this->archiveDir($gameId, $number));
    }

    public function serve(string $publicId, array $files): void
    {
        $final = $this->servedDir($publicId);
        if (file_exists($final)) {
            throw new ArtifactAlreadyExists("served {$publicId}");
        }

        $served = dirname($final);
        $this->ensureDir($served, 0755);
        // Fora do padrão UUID que o nginx serve: invisível até o rename.
        $staging = $served.'/.staging-'.bin2hex(random_bytes(8));
        if (! @mkdir($staging, 0700)) {
            throw new RuntimeException("Não foi possível criar {$staging}.");
        }

        try {
            foreach ($files as $path => $contents) {
                $this->writeNew($staging.'/'.$this->allowed($path), $contents, 0644);
            }
            $this->chmod($staging, 0755);
            if (file_exists($final) || ! @rename($staging, $final)) {
                throw new ArtifactAlreadyExists("served {$publicId}");
            }
        } catch (Throwable $e) {
            $this->removeTree($staging);

            throw $e;
        }
    }

    public function unserve(string $publicId): void
    {
        $dir = $this->servedDir($publicId);
        if (! is_dir($dir)) {
            return;
        }

        // Some da origem de jogos de uma vez; a limpeza vem depois.
        $trash = dirname($dir).'/.removed-'.bin2hex(random_bytes(8));
        if (! @rename($dir, $trash)) {
            throw new RuntimeException("Não foi possível tirar {$publicId} do ar.");
        }
        $this->removeTree($trash);
    }

    public function isServed(string $publicId): bool
    {
        return is_dir($this->servedDir($publicId));
    }

    private function archiveDir(string $gameId, int $number): string
    {
        if (preg_match(self::ULID, $gameId) !== 1 || $number < 1) {
            throw new InvalidArgumentException('Jogo ou número de versão inválido.');
        }

        return "{$this->root}/archive/{$gameId}/v{$number}";
    }

    private function servedDir(string $publicId): string
    {
        if (preg_match(self::UUID, $publicId) !== 1) {
            throw new InvalidArgumentException('publicId inválido.');
        }

        return "{$this->root}/served/{$publicId}";
    }

    private function allowed(string $path): string
    {
        if (! in_array($path, $this->allowedPaths, true)) {
            throw new InvalidArgumentException('Arquivo fora da allowlist.');
        }

        return $path;
    }

    private function writeNew(string $file, string $contents, int $mode): void
    {
        $handle = @fopen($file, 'xb');
        if ($handle === false) {
            throw file_exists($file) ? new ArtifactAlreadyExists($file) : new RuntimeException("Não foi possível criar {$file}.");
        }
        try {
            if (fwrite($handle, $contents) !== strlen($contents) || ! fflush($handle)) {
                throw new RuntimeException("Escrita incompleta em {$file}.");
            }
        } finally {
            fclose($handle);
        }
        $this->chmod($file, $mode);
    }

    private function ensureDir(string $dir, int $mode): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, $mode, true) && ! is_dir($dir)) {
            throw new RuntimeException("Não foi possível criar {$dir}.");
        }
    }

    private function chmod(string $path, int $mode): void
    {
        if (! @chmod($path, $mode)) {
            throw new RuntimeException("Não foi possível ajustar as permissões de {$path}.");
        }
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir) || is_link($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
