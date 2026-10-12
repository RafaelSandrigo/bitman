<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

use App\Modules\Ingestion\Domain\Exceptions\InvalidSnapshot;

/**
 * Uma entrada da árvore do commit, como dado: `path` são os bytes vindos do Git (podem ser
 * hostis) e nunca viram caminho de arquivo nem comando (G6). Para exibir, use `displayPath()`.
 */
final readonly class TreeEntry
{
    public const REGULAR = '100644';

    public const EXECUTABLE = '100755';

    public const SYMLINK = '120000';

    public const SUBMODULE = '160000';

    public function __construct(
        public string $path,
        public string $mode,
        public string $type,
        public string $objectId,
        /** Nulo quando o blob ficou no servidor por passar do limite (ou não é blob). */
        public ?int $size,
    ) {
        if ($path === '' || strlen($path) > 4096) {
            throw new InvalidSnapshot('caminho vazio ou longo demais');
        }
        if (preg_match('/^[0-7]{6}$/D', $mode) !== 1 || ! in_array($type, ['blob', 'tree', 'commit'], true)) {
            throw new InvalidSnapshot('modo ou tipo desconhecido');
        }
        if (preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/D', $objectId) !== 1) {
            throw new InvalidSnapshot('objeto inválido');
        }
        if ($size !== null && $size < 0) {
            throw new InvalidSnapshot('tamanho negativo');
        }
    }

    public function isRegularFile(): bool
    {
        return $this->type === 'blob' && $this->mode === self::REGULAR;
    }

    public function isSymlink(): bool
    {
        return $this->mode === self::SYMLINK;
    }

    public function isSubmodule(): bool
    {
        return $this->mode === self::SUBMODULE || $this->type === 'commit';
    }

    /** O que o admin vê: UTF-8 válido, com bytes de controle e inválidos escapados como `\xNN`. */
    public function displayPath(): string
    {
        return self::display($this->path);
    }

    public static function display(string $raw): string
    {
        $out = '';
        $offset = 0;
        $length = strlen($raw);
        while ($offset < $length) {
            if (preg_match('/\G(?:[\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})/', $raw, $match, 0, $offset) === 1) {
                $out .= $match[0] === '\\' ? '\\\\' : $match[0];
                $offset += strlen($match[0]);

                continue;
            }
            $out .= sprintf('\\x%02X', ord($raw[$offset]));
            $offset++;
        }

        return $out;
    }

    /** Descrição do tipo para as mensagens da política. */
    public function kind(): string
    {
        return match (true) {
            $this->isSymlink() => 'um symlink',
            $this->isSubmodule() => 'um submodule',
            $this->type === 'tree' => 'um diretório',
            $this->mode === self::EXECUTABLE => 'um executável',
            $this->isRegularFile() => 'um arquivo comum',
            default => "um objeto com modo {$this->mode}",
        };
    }
}
