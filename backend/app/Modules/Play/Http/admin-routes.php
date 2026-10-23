<?php

declare(strict_types=1);

use App\Modules\Play\Http\Controllers\Admin\ScoreController;
use Illuminate\Support\Facades\Route;

// Moderação de scores (S2).
Route::get('scores', [ScoreController::class, 'index'])->name('scores.index');
Route::get('scores/summary', [ScoreController::class, 'summary'])->name('scores.summary');
Route::post('scores/{score}/hide', [ScoreController::class, 'hide'])->whereUlid('score')->name('scores.hide');
Route::post('scores/{score}/keep', [ScoreController::class, 'keep'])->whereUlid('score')->name('scores.keep');
Route::post('scores/{score}/restore', [ScoreController::class, 'restore'])->whereUlid('score')->name('scores.restore');
