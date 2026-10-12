<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** I8: a publicação de outra versão tira esta do ar, na mesma transação. */
final readonly class VersionSuperseded implements Auditable
{
    public function __construct(
        public string $versionId,
        public string $gameId,
        public int $number,
        public string $publicId,
        public string $supersededById,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'versioning.version-superseded',
            subjectType: 'game_version',
            subjectId: $this->versionId,
            actorId: $this->actorId,
            context: ['game_id' => $this->gameId, 'number' => $this->number, 'public_id' => $this->publicId, 'superseded_by' => $this->supersededById],
        );
    }
}
