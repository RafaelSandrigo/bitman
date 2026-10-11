<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\User;

it('concede admin pelo console (bootstrap) e audita com ator nulo', function (): void {
    $user = User::factory()->create(['email' => 'rafael@example.com']);

    $this->artisan('user:grant-role', ['email' => 'Rafael@Example.com', 'role' => 'admin'])
        ->expectsOutput('Role admin concedida a rafael@example.com.')
        ->assertSuccessful();

    $entry = AuditEntry::sole();
    expect($user->fresh()->hasRole(Role::Admin))->toBeTrue()
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->ip_address)->toBeNull()
        ->and($entry->context)->toEqual(['role' => 'admin', 'via' => 'console']);
});

it('falha sem efeito para role inválida, usuário inexistente ou e-mail não verificado', function (array $arguments, ?callable $setup): void {
    $setup?->__invoke();

    $this->artisan('user:grant-role', $arguments)->assertFailed();

    expect(AuditEntry::count())->toBe(0);
})->with([
    'role inexistente' => [['email' => 'a@example.com', 'role' => 'root'], fn () => User::factory()->create(['email' => 'a@example.com'])],
    'role player' => [['email' => 'a@example.com', 'role' => 'player'], fn () => User::factory()->create(['email' => 'a@example.com'])],
    'usuário inexistente' => [['email' => 'nada@example.com', 'role' => 'admin'], null],
    'e-mail não verificado' => [['email' => 'a@example.com', 'role' => 'admin'], fn () => User::factory()->unverified()->create(['email' => 'a@example.com'])],
]);
