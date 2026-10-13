<?php

declare(strict_types=1);

namespace App\Modules\Play\Application;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Play\Domain\LeaderboardEntry;

final readonly class GameLeaderboard
{
    /** @param  list<LeaderboardEntry>  $entries */
    public function __construct(
        public Game $game,
        public array $entries,
    ) {}
}
