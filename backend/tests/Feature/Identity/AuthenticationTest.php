<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('autentica com e-mail e senha e encerra a sessão no logout', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/auth/login', ['email' => 'ADA@example.com', 'password' => 'password'])->assertOk();
    $this->assertAuthenticatedAs($user);

    $this->postJson('/api/v1/auth/logout')->assertNoContent();
    $this->assertGuest();
});

it('recusa credenciais inválidas sem revelar se o e-mail existe', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'errada'])
        ->assertUnprocessable()->json('errors.email');
    $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'ninguem@example.com', 'password' => 'errada'])
        ->assertUnprocessable()->json('errors.email');

    expect($wrongPassword)->toBe($unknownEmail);
    $this->assertGuest();
});

it('limita tentativas de login por e-mail + IP (A2)', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => "errada-{$attempt}"])
            ->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password'])
        ->assertTooManyRequests();
    $this->assertGuest();
});

it('expõe o usuário autenticado com roles e permissões em /api/v1/me', function (): void {
    $user = User::factory()->developer()->create();

    $this->actingAs($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => true,
            'roles' => ['player', 'developer'],
            'permissions' => ['games.submit'],
            'created_at' => $user->created_at->toIso8601String(),
        ]]);
});

it('não expõe senha nem token de "lembrar-me"', function (): void {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

it('responde em português quando a conta ainda não confirmou o e-mail', function (): void {
    $user = User::factory()->unverified()->admin()->create();

    $this->actingAs($user)->getJson('/api/v1/admin/users')
        ->assertForbidden()
        ->assertJsonPath('message', 'Confirme seu e-mail para continuar.');
});

it('o banco espelha os enums de roles e permissões, inclusive removendo as que saíram do domínio', function (): void {
    Permission::findOrCreate('obsoleta', 'web');

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::pluck('name')->sort()->values()->all())
        ->toBe(['audit.view', 'games.manage', 'games.submit', 'users.manage-roles', 'versions.authorize-import'])
        ->and(Role::pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'developer', 'player']);
});
