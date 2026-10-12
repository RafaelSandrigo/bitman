<?php

declare(strict_types=1);

use App\Modules\Versioning\Http\Controllers\Admin\ImportDecisionController;
use App\Modules\Versioning\Http\Controllers\Admin\ReviewController;
use App\Modules\Versioning\Http\Controllers\Admin\VersionController;
use Illuminate\Support\Facades\Route;

Route::get('versions', [VersionController::class, 'index'])->name('versions.index');
Route::get('versions/{version}', [VersionController::class, 'show'])->whereUlid('version')->name('versions.show');
Route::post('versions/{version}/authorize-import', [ImportDecisionController::class, 'authorize'])->whereUlid('version')->name('versions.authorize-import');
Route::post('versions/{version}/reject-import', [ImportDecisionController::class, 'reject'])->whereUlid('version')->name('versions.reject-import');
Route::post('games/{game}/versions', [VersionController::class, 'store'])->whereUlid('game')->name('games.versions.store');

// Portão 1 (commit) e Portão 2 (arquivos).
Route::post('versions/{version}/approve-commit', [ReviewController::class, 'approveCommit'])->whereUlid('version')->name('versions.approve-commit');
Route::post('versions/{version}/reject-commit', [ReviewController::class, 'rejectCommit'])->whereUlid('version')->name('versions.reject-commit');
Route::get('versions/{version}/files/{file}/content', [ReviewController::class, 'content'])->whereUlid(['version', 'file'])->name('versions.files.content');
Route::post('versions/{version}/files/{file}/review', [ReviewController::class, 'reviewFile'])->whereUlid(['version', 'file'])->name('versions.files.review');
Route::post('versions/{version}/reject', [ReviewController::class, 'rejectVersion'])->whereUlid('version')->name('versions.reject');
