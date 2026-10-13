<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Play\Application\GameLeaderboard;
use App\Modules\Play\Application\PlayableGames;
use App\Modules\Play\Domain\Leaderboard;

/** Ranking público de um jogo jogável (I9); outro estado responde como inexistente. */
final readonly class ShowLeaderboard
{
    public function __construct(
        private PlayableGames $playableGames,
        private Leaderboard $leaderboard,
    ) {}

    public function __invoke(string $slug): ?GameLeaderboard
    {
        $playable = $this->playableGames->bySlug($slug);

        return $playable === null ? null : new GameLeaderboard($playable[0], $this->leaderboard->top($playable[0]->id));
    }
}
