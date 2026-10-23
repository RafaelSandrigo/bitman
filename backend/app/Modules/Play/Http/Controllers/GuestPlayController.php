<?php

declare(strict_types=1);

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\Actions\ClaimGuestScore;
use App\Modules\Play\Application\Actions\StartGuestPlay;
use App\Modules\Play\Application\Actions\SubmitGuestScore;
use App\Modules\Play\Http\Requests\ClaimGuestScoreRequest;
use App\Modules\Play\Http\Requests\SubmitScoreRequest;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Partida de visitante e o score pendente salvo depois do login (docs/arquitetura.md#partida-de-visitante). */
final class GuestPlayController
{
    public function store(string $slug, StartGuestPlay $startGuestPlay): JsonResponse
    {
        $started = $startGuestPlay($slug);

        return response()->json(['data' => [
            'id' => $started->play->id,
            'token' => $started->token,
            'play_url' => $started->playUrl,
            'started_at' => $started->play->started_at->toIso8601String(),
            'expires_at' => $started->play->expires_at->toIso8601String(),
            'min_seconds' => $started->minSessionSeconds,
        ]], 201)->header('Cache-Control', 'no-store');
    }

    public function score(string $play, SubmitScoreRequest $request, SubmitGuestScore $submitGuestScore): JsonResponse
    {
        $scored = $submitGuestScore($play, $request->token(), $request->score());

        return response()->json(['data' => [
            'value' => $scored->value,
            'claim_expires_at' => $scored->claim_expires_at?->toIso8601String(),
        ]], 201);
    }

    public function claim(string $play, ClaimGuestScoreRequest $request, #[CurrentUser] User $player, ClaimGuestScore $claimGuestScore): JsonResponse
    {
        $claimed = $claimGuestScore($player, $play, $request->token());
        $recorded = $claimed->recorded;

        return response()->json(['data' => [
            'value' => $recorded->score->value,
            'position' => $recorded->position,
            'personal_best' => $recorded->isPersonalBest(),
            'previous_best' => $recorded->previousBest,
            'game' => ['slug' => $claimed->game->slug, 'name' => $claimed->game->name],
        ]], 201);
    }
}
