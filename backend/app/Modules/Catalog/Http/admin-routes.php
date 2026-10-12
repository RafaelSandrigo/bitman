<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controllers\Admin\GameSlugController;
use App\Modules\Catalog\Http\Controllers\Admin\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');
Route::patch('games/{game}/slug', [GameSlugController::class, 'update'])->whereUlid('game')->name('games.slug.update');
