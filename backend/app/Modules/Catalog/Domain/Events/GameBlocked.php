<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** J6: o jogo sai do ar na hora (a versão publicada deixa de ser servida), com motivo (I11). */
final readonly class GameBlocked implements Auditable
{
    public function __construct(
        public string $gameId,
        public ?string $publishedVersionId,
        public string $actorId,
        public string $reason,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'catalog.game-blocked',
            subjectType: 'game',
            subjectId: $this->gameId,
            actorId: $this->actorId,
            context: ['published_version_id' => $this->publishedVersionId],
            reason: $this->reason,
        );
    }
}
