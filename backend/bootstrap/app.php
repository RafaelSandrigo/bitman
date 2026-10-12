<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureEmailIsVerified;
use App\Modules\Versioning\Domain\Exceptions\ArtifactAlreadyExists;
use App\Modules\Versioning\Domain\Exceptions\PublishedContentMismatch;
use App\Modules\Versioning\Domain\Exceptions\QuarantineTampered;
use App\Shared\Domain\BusinessRuleViolation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA: a API aceita a sessão (cookie) vinda da própria origem do hub.
        $middleware->statefulApi();
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
        // Rotas da SPA (o backend não tem páginas).
        $middleware->redirectGuestsTo('/entrar');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Único ponto de mapeamento das regras de negócio para HTTP.
        $exceptions->render(fn (BusinessRuleViolation $e) => response()->json(['message' => $e->getMessage()], 422));
        // P1: quarentena adulterada. Continua sendo reportada; o admin vê o motivo, nunca o conteúdo.
        $exceptions->render(fn (QuarantineTampered $e) => response()->json(['message' => $e->getMessage()], 409));
        // I6, I7: integridade da publicação. Nada foi publicado; o erro continua sendo reportado.
        $exceptions->render(fn (PublishedContentMismatch $e) => response()->json(['message' => $e->getMessage()], 409));
        $exceptions->render(fn (ArtifactAlreadyExists $e) => response()->json(['message' => 'Já existe um artefato para esta versão; nada foi sobrescrito.'], 409));
    })->create();
