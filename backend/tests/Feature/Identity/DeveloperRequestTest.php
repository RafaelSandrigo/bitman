<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Identity\Application\Actions\ApproveDeveloperRequest;
use App\Modules\Identity\Domain\DeveloperRequest;
use App\Modules\Identity\Domain\DeveloperRequestStatus;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\User;
use Illuminate\Auth\Access\AuthorizationException;

/*
| M18: "Seja Desenvolvedor". O player com e-mail confirmado pede a role developer com nome, GitHub
| e uma apresentação; o admin aprova ou rejeita com motivo, tudo auditado.
*/

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create(['name' => 'Rafael']);
    $this->player = User::factory()->create(['name' => 'Ana']);
});

function requestDeveloper(User $user, array $overrides = [])
{
    return test()->actingAs($user)->postJson('/api/v1/me/developer-request', [
        'name' => 'Ana Souza',
        'github_url' => 'https://github.com/ana-souza',
        'bio' => "Faço jogos em canvas há 3 anos.\nQuero publicar um Snake.",
        ...$overrides,
    ]);
}

it('o player pede para ser developer: o pedido fica pendente e auditado', function (): void {
    requestDeveloper($this->player)
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.name', 'Ana Souza')
        ->assertJsonPath('data.github_url', 'https://github.com/ana-souza');

    $request = DeveloperRequest::query()->sole();
    expect($request->user_id)->toBe($this->player->id)
        ->and($request->status)->toBe(DeveloperRequestStatus::Pending)
        ->and($this->player->fresh()?->hasRole(Role::Developer))->toBeFalse()
        ->and(AuditEntry::query()->sole()->action)->toBe('identity.developer-requested');
    $this->getJson('/api/v1/me/developer-request')->assertOk()->assertJsonPath('data.status', 'pending');
});

it('normaliza o perfil do GitHub e recusa outros endereços', function (string $url, ?string $expected): void {
    $response = requestDeveloper($this->player, ['github_url' => $url]);

    if ($expected === null) {
        $response->assertUnprocessable()->assertJsonValidationErrors('github_url');
    } else {
        $response->assertCreated()->assertJsonPath('data.github_url', $expected);
    }
})->with([
    'barra no fim' => ['https://github.com/Ana-Souza/', 'https://github.com/Ana-Souza'],
    'host em maiúsculas' => ['https://GitHub.com/ana', 'https://github.com/ana'],
    'http' => ['http://github.com/ana', null],
    'outro host' => ['https://gitlab.com/ana', null],
    'repositório, não perfil' => ['https://github.com/ana/snake', null],
    'query' => ['https://github.com/ana?tab=repositories', null],
    'usuário inválido' => ['https://github.com/-ana', null],
    'javascript' => ['javascript:alert(1)', null],
]);

it('valida nome e apresentação', function (array $fields, string $error): void {
    requestDeveloper($this->player, $fields)->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'sem nome' => [['name' => ''], 'name'],
    'apresentação curta' => [['bio' => 'oi'], 'bio'],
    'apresentação longa' => [['bio' => str_repeat('a', 1001)], 'bio'],
]);

it('exige e-mail confirmado; quem já é developer não pede', function (): void {
    requestDeveloper(User::factory()->unverified()->create())->assertForbidden();
    requestDeveloper(User::factory()->developer()->create())
        ->assertUnprocessable()
        ->assertJson(['message' => 'Você já é developer.']);

    expect(DeveloperRequest::count())->toBe(0);
});

it('um pedido aberto por usuário', function (): void {
    requestDeveloper($this->player)->assertCreated();
    requestDeveloper($this->player)
        ->assertUnprocessable()
        ->assertJson(['message' => 'Você já tem um pedido em análise.']);
});

it('o admin vê a fila de pendentes', function (): void {
    requestDeveloper($this->player)->assertCreated();

    $this->actingAs($this->admin)->getJson('/api/v1/admin/developer-requests')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user', ['id' => $this->player->id, 'name' => 'Ana', 'email' => $this->player->email])
        ->assertJsonPath('data.0.bio', "Faço jogos em canvas há 3 anos.\nQuero publicar um Snake.");
    $this->actingAs($this->player)->getJson('/api/v1/admin/developer-requests')->assertForbidden();
});

it('aprovar concede a role developer, auditado', function (): void {
    $id = requestDeveloper($this->player)->json('data.id');

    $this->actingAs($this->admin)->postJson("/api/v1/admin/developer-requests/{$id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.decided_by', 'Rafael');

    expect($this->player->fresh()?->hasRole(Role::Developer))->toBeTrue()
        ->and(AuditEntry::query()->orderBy('id')->pluck('action')->all())
        ->toBe(['identity.developer-requested', 'identity.role-granted', 'identity.developer-request-approved']);
    $this->postJson("/api/v1/admin/developer-requests/{$id}/approve")
        ->assertUnprocessable()
        ->assertJson(['message' => 'Este pedido já foi decidido.']);
});

it('rejeitar exige motivo, que o usuário vê; depois ele pode pedir de novo', function (): void {
    $id = requestDeveloper($this->player)->json('data.id');

    $this->actingAs($this->admin)->postJson("/api/v1/admin/developer-requests/{$id}/reject", ['reason' => ''])->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/admin/developer-requests/{$id}/reject", ['reason' => 'Mande um link para um jogo seu.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $entry = AuditEntry::query()->where('action', 'identity.developer-request-rejected')->sole();
    expect($entry->reason)->toBe('Mande um link para um jogo seu.')
        ->and($this->player->fresh()?->hasRole(Role::Developer))->toBeFalse();
    $this->actingAs($this->player)->getJson('/api/v1/me/developer-request')
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.decision_reason', 'Mande um link para um jogo seu.');
    requestDeveloper($this->player)->assertCreated();
});

it('a Action exige users.manage-roles (defesa em profundidade)', function (): void {
    $id = requestDeveloper($this->player)->json('data.id');

    expect(fn () => app(ApproveDeveloperRequest::class)($this->player, $id))->toThrow(AuthorizationException::class)
        ->and($this->player->fresh()?->hasRole(Role::Developer))->toBeFalse();
});

it('sem pedido, responde data nulo', function (): void {
    $this->actingAs($this->player)->getJson('/api/v1/me/developer-request')->assertOk()->assertExactJson(['data' => null]);
});

it('conta os pendentes para o painel', function (): void {
    requestDeveloper($this->player)->assertCreated();

    $this->actingAs($this->admin)->getJson('/api/v1/admin/developer-requests/summary')->assertOk()->assertExactJson(['data' => ['pending' => 1]]);
});
