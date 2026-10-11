<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

// As rotas de cadastro, login, logout, verificação e senha são do Fortify (config/fortify.php).
Route::get('me', MeController::class)->middleware('auth:sanctum')->name('me');
