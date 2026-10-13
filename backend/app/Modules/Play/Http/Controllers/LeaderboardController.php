<?php

declare(strict_types=1);

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Play\Application\Actions\ShowLeaderboard;
use App\Modules\Play\Domain\LeaderboardEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ranking público. `is_me` marca a linha de quem está logado; o id dos jogadores não é exposto. */
final class LeaderboardController
{
    public function __invoke(string $slug, Request $request, ShowLeaderboard $showLeaderboard): JsonResponse
    {
        $leaderboard = $showLeaderboard($slug) ?? abort(404);
        $me = $request->user('sanctum')?->getAuthIdentifier();

        return response()->json([
            'data' => array_map(fn (LeaderboardEntry $entry): array => [
                'position' => $entry->position,
                'player_name' => $entry->playerName,
                'value' => $entry->value,
                'version_number' => $entry->versionNumber,
                'achieved_at' => $entry->achievedAt->toIso8601String(),
                'is_me' => $me !== null && $entry->userId === $me,
            ], $leaderboard->entries),
            'meta' => ['game' => ['slug' => $leaderboard->game->slug, 'name' => $leaderboard->game->name]],
        ]);
    }
}
