<?php

declare(strict_types=1);

use App\Modules\Versioning\Http\Controllers\Admin\ImportDecisionController;
use App\Modules\Versioning\Http\Controllers\Admin\VersionController;
use Illuminate\Support\Facades\Route;

Route::get('versions', [VersionController::class, 'index'])->name('versions.index');
Route::get('versions/{version}', [VersionController::class, 'show'])->whereUlid('version')->name('versions.show');
Route::post('versions/{version}/authorize-import', [ImportDecisionController::class, 'authorize'])->whereUlid('version')->name('versions.authorize-import');
Route::post('versions/{version}/reject-import', [ImportDecisionController::class, 'reject'])->whereUlid('version')->name('versions.reject-import');
Route::post('games/{game}/versions', [VersionController::class, 'store'])->whereUlid('game')->name('games.versions.store');
