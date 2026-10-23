<?php

declare(strict_types=1);

namespace App\Modules\Play\Application;

use App\Modules\Catalog\Domain\Game;

/** Score de visitante salvo na conta: o resultado de sempre e o jogo, para o aviso fora da página dele. */
final readonly class ClaimedScore
{
    public function __construct(
        public RecordedScore $recorded,
        public Game $game,
    ) {}
}
