<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

/** Distribuição dos scores visíveis de um jogo, base da sinalização de anomalias (S2). */
final readonly class ScoreStatistics
{
    public function __construct(
        public int $count,
        /** Percentil 99 dos valores; null sem nenhum score. */
        public ?int $p99,
        /** M15: percentil 99 dos pontos por segundo de partida; null sem nenhum score. */
        public ?float $p99PerSecond = null,
    ) {}
}
