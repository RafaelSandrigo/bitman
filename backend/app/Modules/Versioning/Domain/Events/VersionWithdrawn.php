<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** J6: o admin retira a versão publicada, com motivo (I11). Ela deixa de ser servida na hora. */
final readonly class VersionWithdrawn implements Auditable
{
    public function __construct(
        public string $versionId,
        public string $gameId,
        public int $number,
        public string $publicId,
        public string $actorId,
        public string $reason,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'versioning.version-withdrawn',
            subjectType: 'game_version',
            subjectId: $this->versionId,
            actorId: $this->actorId,
            context: ['game_id' => $this->gameId, 'number' => $this->number, 'public_id' => $this->publicId],
            reason: $this->reason,
        );
    }
}
