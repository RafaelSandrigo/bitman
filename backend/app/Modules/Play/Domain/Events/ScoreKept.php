<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** M20: o admin conferiu um score sinalizado e o manteve no ranking. */
final readonly class ScoreKept implements Auditable
{
    public function __construct(
        public string $scoreId,
        public string $gameId,
        public string $playerId,
        public int $value,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'play.score-kept',
            subjectType: 'score',
            subjectId: $this->scoreId,
            actorId: $this->actorId,
            context: ['game_id' => $this->gameId, 'player_id' => $this->playerId, 'value' => $this->value],
        );
    }
}
