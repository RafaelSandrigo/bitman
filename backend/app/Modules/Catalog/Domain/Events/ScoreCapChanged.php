<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** M15: o admin definiu, mudou ou removeu o teto de score do jogo. */
final readonly class ScoreCapChanged implements Auditable
{
    public function __construct(
        public string $gameId,
        public ?int $from,
        public ?int $to,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'catalog.score-cap-changed',
            subjectType: 'game',
            subjectId: $this->gameId,
            actorId: $this->actorId,
            context: ['from' => $this->from, 'to' => $this->to],
        );
    }
}
