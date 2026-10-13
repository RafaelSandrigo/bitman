<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

/**
 * S2: sinaliza scores fora do padrão do jogo (acima do p99 × fator). O score continua registrado
 * e no ranking; o admin decide se o oculta. Com poucos scores ainda não há padrão, e nada é sinalizado.
 */
final readonly class AnomalyPolicy
{
    public function __construct(
        public float $factor,
        public int $minSample,
    ) {}

    public function flagReason(ScoreValue $score, ScoreStatistics $statistics): ?string
    {
        if ($statistics->count < $this->minSample || $statistics->p99 === null) {
            return null;
        }

        $threshold = max($statistics->p99, 1) * $this->factor;
        if ($score->value <= $threshold) {
            return null;
        }

        return sprintf('Acima de %g × o p99 do jogo (%d, com %d scores).', $this->factor, $statistics->p99, $statistics->count);
    }
}
