<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\GameSubmissionController;
use App\Modules\Catalog\Http\Controllers\MyGamesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::post('games', [GameSubmissionController::class, 'store'])->middleware('throttle:game-submissions')->name('games.store');
    Route::get('me/games', MyGamesController::class)->name('me.games');
});
