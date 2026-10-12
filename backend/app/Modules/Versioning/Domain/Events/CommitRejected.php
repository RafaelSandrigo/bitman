<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

final readonly class CommitRejected implements Auditable
{
    public function __construct(
        public string $versionId,
        public string $gameId,
        public int $number,
        public string $commitSha,
        public string $actorId,
        public string $reason,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'versioning.commit-rejected',
            subjectType: 'game_version',
            subjectId: $this->versionId,
            actorId: $this->actorId,
            context: ['game_id' => $this->gameId, 'number' => $this->number, 'commit' => $this->commitSha],
            reason: $this->reason,
        );
    }
}
