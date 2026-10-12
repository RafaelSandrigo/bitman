<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Ingestion\Domain\PolicyLimits;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\TreeEntry;
use App\Shared\Domain\Git\CommitSha;

/** Snapshots montados à mão para os testes da política (sem sandbox). */
final class Snapshots
{
    public const COMMIT = 'a84c7f91d2e3b4a5968778695a4b3c2d1e0f9a8b';

    /** @return array<string, string> */
    public static function gameFiles(): array
    {
        return [
            'index.html' => "<!doctype html>\n<title>Snake</title>\n",
            'style.css' => "body { margin: 0; }\n",
            'game.js' => "console.log('olá');\n",
        ];
    }

    public static function limits(int $maxFileBytes = 1024, int $maxTreeEntries = 20): PolicyLimits
    {
        return new PolicyLimits(['index.html', 'style.css', 'game.js'], $maxFileBytes, $maxTreeEntries);
    }

    /**
     * Arquivos regulares com conteúdo entregue, mais entradas extras (só árvore).
     *
     * @param  array<string, string>  $files  path => conteúdo (vira blob entregue)
     * @param  list<TreeEntry>  $extra
     */
    public static function of(array $files, array $extra = []): RepositorySnapshot
    {
        $entries = [];
        foreach ($files as $path => $content) {
            $entries[] = self::file((string) $path, $content);
        }

        return new RepositorySnapshot(CommitSha::fromString(self::COMMIT), [...$entries, ...$extra], $files);
    }

    public static function valid(): RepositorySnapshot
    {
        return self::of(self::gameFiles());
    }

    /** O jogo válido trocando uma entrada (ex.: game.js como symlink), sem o blob dela. */
    public static function replacing(string $path, TreeEntry $entry): RepositorySnapshot
    {
        $files = self::gameFiles();
        unset($files[$path]);

        return self::of($files, [$entry]);
    }

    public static function file(string $path, string $content, string $mode = TreeEntry::REGULAR): TreeEntry
    {
        return new TreeEntry($path, $mode, 'blob', RepositorySnapshot::gitBlobId($content), strlen($content));
    }

    /** Entrada sem conteúdo entregue (tamanho opcional: nulo = acima do limite do fetch). */
    public static function entry(string $path, string $mode = TreeEntry::REGULAR, ?int $size = 10, string $type = 'blob'): TreeEntry
    {
        return new TreeEntry($path, $mode, $type, sha1($path), $size);
    }
}
