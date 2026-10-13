<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** O admin devolveu ao ranking um score que estava oculto. */
final readonly class ScoreRestored implements Auditable
{
    public function __construct(
        public string $scoreId,
        public string $gameId,
        public string $playerId,
        public int $value,
        public bool $flagged,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'play.score-restored',
            subjectType: 'score',
            subjectId: $this->scoreId,
            actorId: $this->actorId,
            context: ['game_id' => $this->gameId, 'player_id' => $this->playerId, 'value' => $this->value, 'flagged' => $this->flagged],
        );
    }
}
