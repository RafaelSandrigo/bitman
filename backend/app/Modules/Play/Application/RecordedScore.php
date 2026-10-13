<?php

declare(strict_types=1);

namespace App\Modules\Play\Application;

use App\Modules\Play\Domain\Score;

/** Resposta ao jogador depois do score: posição no ranking e se bateu o próprio recorde. */
final readonly class RecordedScore
{
    public function __construct(
        public Score $score,
        /** null se o score não aparece no ranking (ex.: o melhor do jogador foi ocultado e este é menor). */
        public ?int $position,
        public ?int $previousBest,
    ) {}

    public function isPersonalBest(): bool
    {
        return $this->previousBest === null || $this->score->value > $this->previousBest;
    }
}
