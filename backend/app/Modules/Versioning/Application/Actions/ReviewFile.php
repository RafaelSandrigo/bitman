<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\FileDecision;
use App\Modules\Versioning\Domain\FileReview;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/**
 * Portão 2 — decisão sobre um arquivo, vinculada ao SHA-256 que o admin analisou (I4, I5, I11).
 * Rejeitar um arquivo obrigatório rejeita a versão, e a quarentena dela é descartada.
 */
final readonly class ReviewFile
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
        private QuarantineStore $quarantine,
    ) {}

    public function __invoke(User $actor, string $versionId, string $fileId, string $analyzedSha256, FileDecision $decision, ?DecisionReason $reason): FileReview
    {
        $this->gate->forUser($actor)->authorize(Permission::ReviewFiles->value);

        [$version, $review] = $this->db->transaction(function () use ($actor, $versionId, $fileId, $analyzedSha256, $decision, $reason): array {
            $version = GameVersion::query()->lockForUpdate()->findOrFail($versionId);
            $file = VersionFile::query()->where('game_version_id', $version->id)->findOrFail($fileId);
            $review = $version->reviewFile($actor, $file, $analyzedSha256, $decision, $reason);
            $this->publisher->publishFrom($version);

            return [$version, $review];
        });

        if ($version->status === VersionStatus::Rejected) {
            $this->quarantine->purge($version->id);
        }

        return $review;
    }
}
