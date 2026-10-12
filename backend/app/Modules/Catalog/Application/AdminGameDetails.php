<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Game;

final readonly class AdminGameDetails
{
    public function __construct(
        public Game $game,
        /** O diretório da versão publicada existe na origem de jogos agora (J6). */
        public bool $isServed,
    ) {}
}
