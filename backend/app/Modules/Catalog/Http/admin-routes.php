<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\Admin\GameController;
use App\Modules\Catalog\Http\Controllers\Admin\GameScoreCapController;
use App\Modules\Catalog\Http\Controllers\Admin\GameSlugController;
use App\Modules\Catalog\Http\Controllers\Admin\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
Route::patch('games/{game}/slug', [GameSlugController::class, 'update'])->whereUlid('game')->name('games.slug.update');
Route::patch('games/{game}/score-cap', [GameScoreCapController::class, 'update'])->whereUlid('game')->name('games.score-cap.update');
Route::get('games/{game}', [GameController::class, 'show'])->whereUlid('game')->name('games.show');
Route::post('games/{game}/block', [GameController::class, 'block'])->whereUlid('game')->name('games.block');
Route::post('games/{game}/unblock', [GameController::class, 'unblock'])->whereUlid('game')->name('games.unblock');
