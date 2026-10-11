<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Identity\Application\Actions\PromoteToDeveloper;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\User;
use Illuminate\Auth\Access\AuthorizationException;

it('o admin promove um player a developer e a concessão gera um AuditEntry', function (): void {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->postJson("/api/v1/admin/users/{$player->id}/developer")
        ->assertOk()
        ->assertJsonPath('data.roles', ['player', 'developer']);

    $entry = AuditEntry::sole();
    expect($player->fresh()->hasRole(Role::Developer))->toBeTrue()
        ->and($entry->action)->toBe('identity.role-granted')
        ->and($entry->subject_type)->toBe('user')
        ->and($entry->subject_id)->toBe($player->id)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->ip_address)->toBe('203.0.113.7')
        ->and($entry->context)->toEqual(['role' => 'developer', 'via' => 'panel']);
});

it('o admin rebaixa um developer e o rebaixamento é auditado', function (): void {
    $admin = User::factory()->admin()->create();
    $developer = User::factory()->developer()->create();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$developer->id}/developer")
        ->assertOk()
        ->assertJsonPath('data.roles', ['player']);

    expect(AuditEntry::sole()->action)->toBe('identity.role-revoked');
});

it('o admin pode promover a si mesmo a developer', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$admin->id}/developer")->assertOk();
});

it('não promove quem ainda não verificou o e-mail (A5)', function (): void {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->unverified()->create();

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$player->id}/developer")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O usuário precisa verificar o e-mail antes de receber esta role.');

    expect(AuditEntry::count())->toBe(0);
});

it('rejeita promover quem já é developer e rebaixar quem não é', function (): void {
    $admin = User::factory()->admin()->create();
    $developer = User::factory()->developer()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$developer->id}/developer")->assertUnprocessable();
    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$player->id}/developer")->assertUnprocessable();

    expect(AuditEntry::count())->toBe(0);
});

it('responde 404 para usuário inexistente', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson('/api/v1/admin/users/01JAAAAAAAAAAAAAAAAAAAAAAA/developer')->assertNotFound();
});

it('a Action recusa quem não é admin mesmo sem passar pela rota (I12, defesa em profundidade)', function (string $role): void {
    $actor = User::factory()->{$role}()->create();
    $player = User::factory()->create();

    expect(fn () => app(PromoteToDeveloper::class)($actor, $player->id))->toThrow(AuthorizationException::class)
        ->and($player->fresh()->hasRole(Role::Developer))->toBeFalse()
        ->and(AuditEntry::count())->toBe(0);
})->with(['developer', 'unverified']);

it('lista e busca usuários para o painel', function (): void {
    $admin = User::factory()->admin()->create(['name' => 'Admin']);
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    User::factory()->create(['name' => 'Alan Turing', 'email' => 'alan@example.com']);

    $this->actingAs($admin)->getJson('/api/v1/admin/users?search=lovelace')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'ada@example.com')
        ->assertJsonPath('meta.total', 1);

    $this->actingAs($admin)->getJson('/api/v1/admin/users?search=100%')->assertOk()->assertJsonCount(0, 'data');
});
