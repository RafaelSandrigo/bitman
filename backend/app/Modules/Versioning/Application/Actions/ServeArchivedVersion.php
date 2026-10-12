<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\Exceptions\PublishedContentMismatch;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;

/**
 * Volta a servir a versão publicada a partir do arquivo permanente (depois de um desbloqueio).
 * Confere de novo o SHA-256 de cada arquivo contra o hash aprovado (I6): um archive adulterado
 * nunca volta ao ar. Roda como sistema, depois do commit da decisão do admin.
 */
final readonly class ServeArchivedVersion
{
    public function __construct(private ArtifactStore $artifacts) {}

    public function __invoke(string $versionId): void
    {
        $version = GameVersion::query()->with('files')->findOrFail($versionId);
        if ($version->status !== VersionStatus::Published || $version->public_id === null || $this->artifacts->isServed($version->public_id)) {
            return;
        }

        $paths = array_values($version->files->map(fn (VersionFile $file): string => $file->path)->all());
        $contents = $this->artifacts->readArchive($version->game_id, $version->number, $paths);
        foreach ($version->files as $file) {
            if (! hash_equals($file->sha256, hash('sha256', $contents[$file->path] ?? ''))) {
                throw new PublishedContentMismatch($file->path);
            }
        }

        $this->artifacts->serve($version->public_id, $contents);
    }
}
