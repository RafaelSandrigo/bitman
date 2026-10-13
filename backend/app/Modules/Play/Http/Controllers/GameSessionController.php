<?php

declare(strict_types=1);

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\Actions\StartGameSession;
use App\Modules\Play\Application\Actions\SubmitScore;
use App\Modules\Play\Http\Requests\SubmitScoreRequest;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Início da partida e envio do score (docs/arquitetura.md#partida-e-score). */
final class GameSessionController
{
    public function store(string $slug, #[CurrentUser] User $player, StartGameSession $startGameSession): JsonResponse
    {
        $started = $startGameSession($player, $slug);

        return response()->json(['data' => [
            'id' => $started->session->id,
            'token' => $started->token,
            'play_url' => $started->playUrl,
            'started_at' => $started->session->started_at->toIso8601String(),
            'expires_at' => $started->session->expires_at->toIso8601String(),
            'min_seconds' => $started->minSessionSeconds,
        ]], 201)->header('Cache-Control', 'no-store');
    }

    public function score(string $session, SubmitScoreRequest $request, #[CurrentUser] User $player, SubmitScore $submitScore): JsonResponse
    {
        $recorded = $submitScore($player, $session, $request->token(), $request->score());

        return response()->json(['data' => [
            'value' => $recorded->score->value,
            'position' => $recorded->position,
            'personal_best' => $recorded->isPersonalBest(),
            'previous_best' => $recorded->previousBest,
        ]], 201);
    }
}
