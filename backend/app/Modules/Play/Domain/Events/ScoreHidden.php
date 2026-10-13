<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** O admin tirou o score do ranking (S2), com motivo (I11). Ele continua registrado. */
final readonly class ScoreHidden implements Auditable
{
    public function __construct(
        public string $scoreId,
        public string $gameId,
        public string $playerId,
        public int $value,
        public bool $flagged,
        public string $actorId,
        public string $reason,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'play.score-hidden',
            subjectType: 'score',
            subjectId: $this->scoreId,
            actorId: $this->actorId,
            context: ['game_id' => $this->gameId, 'player_id' => $this->playerId, 'value' => $this->value, 'flagged' => $this->flagged],
            reason: $this->reason,
        );
    }
}
