<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\FileContent;
use App\Modules\Versioning\Application\PreviousFile;
use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\ChangeType;
use App\Modules\Versioning\Domain\Exceptions\FileContentUnavailable;
use App\Modules\Versioning\Domain\Exceptions\QuarantineTampered;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Contracts\Auth\Access\Gate;
use RuntimeException;

/**
 * Portão 2 — o conteúdo que o admin vai analisar. Lido da quarentena e conferido contra o hash
 * registrado no fetch (I13, P1): o que aparece na tela é exatamente o que será aprovado (I5).
 * Um arquivo `modified` vem com o da versão publicada, para o diff (M7).
 */
final readonly class ShowFileContent
{
    public function __construct(
        private Gate $gate,
        private QuarantineStore $quarantine,
        private ArtifactStore $artifacts,
    ) {}

    public function __invoke(User $actor, string $versionId, string $fileId): FileContent
    {
        $this->gate->forUser($actor)->authorize(Permission::ReviewFiles->value);

        $version = GameVersion::query()->findOrFail($versionId);
        $file = VersionFile::query()->where('game_version_id', $version->id)->findOrFail($fileId);
        if (! in_array($version->status, [VersionStatus::AwaitingCommitReview, VersionStatus::AwaitingFileReview], true)) {
            throw new FileContentUnavailable;
        }

        $content = $this->quarantine->get($version->id, $file->sha256);
        if (! hash_equals($file->sha256, hash('sha256', $content)) || ! mb_check_encoding($content, 'UTF-8')) {
            throw new QuarantineTampered;
        }

        if ($file->change_type !== ChangeType::Modified) {
            return new FileContent($file, $content);
        }
        $previous = $this->previousFile($version, $file);

        return new FileContent($file, $content, $previous, $previous === null);
    }

    /** M7: a versão no ar do jogo, do archive (write-once, I7) e conferida pelo hash aprovado (I6). */
    private function previousFile(GameVersion $version, VersionFile $file): ?PreviousFile
    {
        $published = GameVersion::query()->where('game_id', $version->game_id)->where('status', VersionStatus::Published)->first();
        $approved = $published?->files()->where('path', $file->path)->first();
        if ($published === null || $approved === null) {
            return null;
        }

        try {
            $archived = $this->artifacts->readArchive($published->game_id, $published->number, [$file->path])[$file->path];
        } catch (RuntimeException) {
            return null;
        }

        return hash_equals($approved->sha256, hash('sha256', $archived)) && mb_check_encoding($archived, 'UTF-8')
            ? new PreviousFile($published->number, $archived)
            : null;
    }
}
