<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Admin\DeveloperRequestController;
use App\Modules\Identity\Http\Controllers\Admin\DeveloperRoleController;
use App\Modules\Identity\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::post('users/{user}/developer', [DeveloperRoleController::class, 'store'])->whereUlid('user')->name('users.developer.store');
Route::get('developer-requests', [DeveloperRequestController::class, 'index'])->name('developer-requests.index');
Route::get('developer-requests/summary', [DeveloperRequestController::class, 'summary'])->name('developer-requests.summary');
Route::post('developer-requests/{developerRequest}/approve', [DeveloperRequestController::class, 'approve'])->whereUlid('developerRequest')->name('developer-requests.approve');
Route::post('developer-requests/{developerRequest}/reject', [DeveloperRequestController::class, 'reject'])->whereUlid('developerRequest')->name('developer-requests.reject');
Route::delete('users/{user}/developer', [DeveloperRoleController::class, 'destroy'])->whereUlid('user')->name('users.developer.destroy');
