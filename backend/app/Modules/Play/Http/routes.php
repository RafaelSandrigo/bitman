<?php

declare(strict_types=1);

use App\Modules\Play\Http\Controllers\GameSessionController;
use App\Modules\Play\Http\Controllers\LeaderboardController;
use App\Modules\Play\Http\Controllers\MyScoresController;
use Illuminate\Support\Facades\Route;

// Ranking público de um jogo jogável (I9).
Route::get('games/{slug}/leaderboard', LeaderboardController::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('games.leaderboard');

// A5: só conta com e-mail confirmado joga valendo score.
Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::post('games/{slug}/sessions', [GameSessionController::class, 'store'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
        ->middleware('throttle:game-sessions')
        ->name('games.sessions.store');
    Route::post('game-sessions/{session}/score', [GameSessionController::class, 'score'])
        ->whereUlid('session')
        ->middleware('throttle:score-submissions')
        ->name('game-sessions.score');
    Route::get('me/scores', MyScoresController::class)->name('me.scores');
});
