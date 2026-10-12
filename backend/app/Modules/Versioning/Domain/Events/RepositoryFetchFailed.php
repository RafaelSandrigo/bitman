<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

final readonly class RepositoryFetchFailed implements Auditable
{
    public function __construct(
        public string $versionId,
        public string $gameId,
        public string $failure,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'versioning.fetch-failed',
            subjectType: 'game_version',
            subjectId: $this->versionId,
            actorId: null,
            context: ['game_id' => $this->gameId, 'failure' => $this->failure],
        );
    }
}
