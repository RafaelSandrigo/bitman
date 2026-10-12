<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure;

use App\Modules\Versioning\Domain\QuarantineStore;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;

/**
 * Quarentena no volume `quarantine` (nunca servido): `{versionId}/{sha256}`. Os dois segmentos
 * são nossos (ULID e hash), então nenhum nome vindo do Git chega ao sistema de arquivos (G6).
 */
final readonly class FilesystemQuarantineStore implements QuarantineStore
{
    public function __construct(private Filesystem $disk) {}

    public function put(string $versionId, string $contents): string
    {
        $sha256 = hash('sha256', $contents);
        $this->disk->put($this->path($versionId, $sha256), $contents);

        return $sha256;
    }

    public function get(string $versionId, string $sha256): string
    {
        return (string) $this->disk->get($this->path($versionId, $sha256));
    }

    public function purge(string $versionId): void
    {
        $this->disk->deleteDirectory(self::versionDirectory($versionId));
    }

    private function path(string $versionId, string $sha256): string
    {
        if (preg_match('/^[0-9a-f]{64}$/D', $sha256) !== 1) {
            throw new InvalidArgumentException('SHA-256 inválido.');
        }

        return self::versionDirectory($versionId).'/'.$sha256;
    }

    private static function versionDirectory(string $versionId): string
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/iD', $versionId) !== 1) {
            throw new InvalidArgumentException('Id de versão inválido.');
        }

        return strtolower($versionId);
    }
}
