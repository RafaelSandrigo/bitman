<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

/**
 * S2: sinaliza scores fora do padrão do jogo. O score continua registrado, fora do ranking até o
 * admin decidir (M20). Três regras, nesta ordem:
 * - acima do teto do jogo, quando o admin definiu um (M15), mesmo sem amostra;
 * - acima do p99 dos valores × fator;
 * - acima do p99 dos pontos por segundo de partida × fator (M15): pega a partida curta demais
 *   para o score que diz ter feito.
 * As duas últimas esperam a amostra mínima: com poucos scores ainda não há padrão.
 */
final readonly class AnomalyPolicy
{
    public function __construct(
        public float $factor,
        public int $minSample,
    ) {}

    /** @param  int  $seconds  duração da partida; `0` quando não se sabe (a regra de ritmo não se aplica) */
    public function flagReason(ScoreValue $score, ScoreStatistics $statistics, int $seconds = 0, ?int $cap = null): ?string
    {
        if ($cap !== null && $score->value > $cap) {
            return sprintf('Acima do teto do jogo (%d).', $cap);
        }
        if ($statistics->count < $this->minSample) {
            return null;
        }

        return $this->aboveValuePattern($score, $statistics) ?? $this->aboveRatePattern($score, $statistics, $seconds);
    }

    private function aboveValuePattern(ScoreValue $score, ScoreStatistics $statistics): ?string
    {
        if ($statistics->p99 === null || $score->value <= max($statistics->p99, 1) * $this->factor) {
            return null;
        }

        return sprintf('Acima de %g × o p99 do jogo (%d, com %d scores).', $this->factor, $statistics->p99, $statistics->count);
    }

    private function aboveRatePattern(ScoreValue $score, ScoreStatistics $statistics, int $seconds): ?string
    {
        if ($seconds <= 0 || $statistics->p99PerSecond === null) {
            return null;
        }

        $rate = $score->value / $seconds;
        if ($rate <= max($statistics->p99PerSecond, 1.0) * $this->factor) {
            return null;
        }

        return sprintf('Acima de %g × o p99 de pontos por segundo do jogo (%s/s, com %d scores): %s/s.', $this->factor, self::rate($statistics->p99PerSecond), $statistics->count, self::rate($rate));
    }

    private static function rate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 1, '.', ''), '0'), '.');
    }
}
