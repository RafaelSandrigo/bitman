<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/*
| I12: nenhum usuário sem role admin executa ação administrativa, por qualquer rota.
| O teste percorre TODAS as rotas em /api/v1/admin, inclusive as que forem criadas
| nas próximas fases, sem precisar ser atualizado.
*/

/** @return list<array{string, string}> */
function adminRoutes(): array
{
    $routes = [];
    foreach (Router::getRoutes()->getRoutes() as $route) {
        /** @var Route $route */
        if (! str_starts_with($route->uri(), 'api/v1/admin')) {
            continue;
        }

        $uri = preg_replace('/\{[^}]+\}/', '01JAAAAAAAAAAAAAAAAAAAAAAA', $route->uri());
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $routes[] = [$method, '/'.$uri];
        }
    }

    return $routes;
}

it('existem rotas administrativas para verificar', function (): void {
    expect(count(adminRoutes()))->toBeGreaterThanOrEqual(4);
});

it('toda rota administrativa exige o grupo admin (sessão, e-mail verificado e role admin)', function (): void {
    foreach (Router::getRoutes()->getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'api/v1/admin')) {
            expect($route->gatherMiddleware())->toContain('auth:sanctum', 'verified', 'role:admin');
        }
    }
});

it('visitante recebe 401 em toda rota administrativa (I12)', function (): void {
    foreach (adminRoutes() as [$method, $uri]) {
        $this->json($method, $uri)->assertUnauthorized();
    }
});

it('player sem e-mail verificado e developer recebem 403 em toda rota administrativa (I12)', function (string $state): void {
    $user = User::factory()->{$state}()->create();

    foreach (adminRoutes() as [$method, $uri]) {
        $this->actingAs($user)->json($method, $uri)->assertForbidden();
    }
})->with(['unverified', 'developer']);

it('player verificado recebe 403 em toda rota administrativa (I12)', function (): void {
    $user = User::factory()->create();

    foreach (adminRoutes() as [$method, $uri]) {
        $this->actingAs($user)->json($method, $uri)->assertForbidden();
    }
});

it('admin sem e-mail verificado também é barrado', function (): void {
    $user = User::factory()->unverified()->admin()->create();

    foreach (adminRoutes() as [$method, $uri]) {
        $this->actingAs($user)->json($method, $uri)->assertForbidden();
    }
});
