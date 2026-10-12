<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** Fetch concluído pelo sistema: o commit analisado fica registrado (I13). */
final readonly class RepositoryFetched implements Auditable
{
    public function __construct(
        public string $versionId,
        public string $gameId,
        public string $commitSha,
        public bool $blocked,
        public int $violations,
        public int $files,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'versioning.repository-fetched',
            subjectType: 'game_version',
            subjectId: $this->versionId,
            actorId: null,
            context: [
                'game_id' => $this->gameId,
                'commit' => $this->commitSha,
                'blocked' => $this->blocked,
                'violations' => $this->violations,
                'files' => $this->files,
            ],
        );
    }
}
