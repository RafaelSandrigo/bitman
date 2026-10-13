<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

interface ScoreDistribution
{
    /** Estatísticas dos scores não ocultos do jogo. */
    public function of(string $gameId): ScoreStatistics;
}
