<?php

declare(strict_types=1);

use App\Modules\Versioning\Http\Controllers\MyVersionsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::get('me/versions', [MyVersionsController::class, 'index'])->name('me.versions');
    Route::get('me/games/{game}/versions', [MyVersionsController::class, 'history'])->whereUlid('game')->name('me.games.versions.index');
    Route::post('me/games/{game}/versions', [MyVersionsController::class, 'store'])
        ->whereUlid('game')
        ->middleware('throttle:game-submissions')
        ->name('me.games.versions.store');
});
