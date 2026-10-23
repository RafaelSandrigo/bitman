<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\MyDeveloperRequestController;
use Illuminate\Support\Facades\Route;

// As rotas de cadastro, login, logout, verificação e senha são do Fortify (config/fortify.php).
Route::get('me', MeController::class)->middleware('auth:sanctum')->name('me');

// M18: "Seja Desenvolvedor". Só com e-mail confirmado; limite de pedidos por usuário.
Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::get('me/developer-request', [MyDeveloperRequestController::class, 'show'])->name('me.developer-request.show');
    Route::post('me/developer-request', [MyDeveloperRequestController::class, 'store'])
        ->middleware('throttle:developer-requests')
        ->name('me.developer-request.store');
});
