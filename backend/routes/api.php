<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| API v1. Cada módulo declara suas rotas; as administrativas entram SEMPRE pelo grupo
| "admin" abaixo, que exige sessão, e-mail verificado e role admin (I12). A Action
| ainda checa a permissão específica pelo Gate (defesa em profundidade).
*/
Route::prefix('v1')->name('v1.')->group(function (): void {
    require app_path('Modules/Identity/Http/routes.php');
    require app_path('Modules/Catalog/Http/routes.php');
    require app_path('Modules/Versioning/Http/routes.php');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['auth:sanctum', 'verified', 'role:admin'])
        ->group(function (): void {
            require app_path('Modules/Identity/Http/admin-routes.php');
            require app_path('Modules/Audit/Http/admin-routes.php');
            require app_path('Modules/Catalog/Http/admin-routes.php');
            require app_path('Modules/Versioning/Http/admin-routes.php');
        });
});
