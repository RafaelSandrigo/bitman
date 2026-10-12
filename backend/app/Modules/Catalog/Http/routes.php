<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\GameCatalogController;
use App\Modules\Catalog\Http\Controllers\GameSubmissionController;
use App\Modules\Catalog\Http\Controllers\MyGamesController;
use Illuminate\Support\Facades\Route;

// Catálogo público (I9): só jogos publicados, sem login.
Route::get('games', [GameCatalogController::class, 'index'])->name('games.index');
Route::get('games/{slug}', [GameCatalogController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('games.show');

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::post('games', [GameSubmissionController::class, 'store'])->middleware('throttle:game-submissions')->name('games.store');
    Route::get('me/games', MyGamesController::class)->name('me.games');
});
