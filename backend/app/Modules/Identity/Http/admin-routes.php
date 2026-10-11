<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Admin\DeveloperRoleController;
use App\Modules\Identity\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::post('users/{user}/developer', [DeveloperRoleController::class, 'store'])->whereUlid('user')->name('users.developer.store');
Route::delete('users/{user}/developer', [DeveloperRoleController::class, 'destroy'])->whereUlid('user')->name('users.developer.destroy');
