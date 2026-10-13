<?php

declare(strict_types=1);

namespace App\Modules\Play\Http\Controllers\Admin;

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\Actions\HideScore;
use App\Modules\Play\Application\Actions\ListScores;
use App\Modules\Play\Application\Actions\RestoreScore;
use App\Modules\Play\Domain\Score;
use App\Modules\Play\Http\Requests\HideScoreRequest;
use App\Modules\Play\Http\Resources\AdminScoreResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Moderação de scores (S2): sinalizados, ocultos e todos. */
final class ScoreController
{
    public function index(Request $request, #[CurrentUser] User $actor, ListScores $listScores): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'filter' => ['nullable', Rule::in([ListScores::FLAGGED, ListScores::HIDDEN, ListScores::ALL])],
            'game_id' => ['nullable', 'ulid'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filter = is_string($validated['filter'] ?? null) ? $validated['filter'] : ListScores::FLAGGED;
        $gameId = is_string($validated['game_id'] ?? null) ? strtolower($validated['game_id']) : null;

        return AdminScoreResource::collection($listScores($actor, $filter, $gameId));
    }

    public function hide(string $score, HideScoreRequest $request, #[CurrentUser] User $actor, HideScore $hideScore): AdminScoreResource
    {
        return new AdminScoreResource($this->withRelations($hideScore($actor, $score, $request->reason())));
    }

    public function restore(string $score, #[CurrentUser] User $actor, RestoreScore $restoreScore): AdminScoreResource
    {
        return new AdminScoreResource($this->withRelations($restoreScore($actor, $score)));
    }

    private function withRelations(Score $score): Score
    {
        return $score->load(['game', 'version', 'player', 'session', 'hiddenBy']);
    }
}
