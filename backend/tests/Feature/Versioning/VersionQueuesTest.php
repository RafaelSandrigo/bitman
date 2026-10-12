<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Versioning\Application\Actions\AuthorizeImport;
use App\Modules\Versioning\Application\Actions\ImportRepository;
use App\Modules\Versioning\Application\Actions\RequestNewImport;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Support\Facades\Queue;

/*
| Filas do admin: contadores por estado e a tag "Novo" (nenhum admin viu a versão no estado atual).
*/

beforeEach(function (): void {
    Queue::fake();
});

function seen(GameVersion $version): GameVersion
{
    $version->markSeen();

    return $version;
}

it('conta o total e os novos de cada estado, inclusive os vazios', function (): void {
    GameVersion::factory()->count(2)->create();
    seen(GameVersion::factory()->create());
    seen(GameVersion::factory()->fetchFailed()->create());

    $response = $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/admin/versions/summary')->assertOk();

    expect($response->json('data.import_requested'))->toBe(['total' => 3, 'new' => 2])
        ->and($response->json('data.fetch_failed'))->toBe(['total' => 1, 'new' => 0])
        ->and($response->json('data.rejected'))->toBe(['total' => 0, 'new' => 0])
        ->and(array_keys($response->json('data')))->toBe(array_map(fn (VersionStatus $s): string => $s->value, VersionStatus::cases()));
});

it('a submissão do dono e o resultado da busca chegam como novos; a decisão de um admin já nasce vista', function (): void {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->developer()->create();
    $game = Game::factory()->for($owner, 'owner')->create();
    GameVersion::factory()->for($game)->validationFailed()->create();

    $version = app(RequestNewImport::class)($owner, $game->id);
    expect($version->status_seen_at)->toBeNull();

    seen($version);
    app(AuthorizeImport::class)($admin, $version->id);
    expect($version->fresh()?->status_seen_at)->not->toBeNull();

    app(ImportRepository::class)->fail($version->id, FetchFailure::Timeout);
    expect($version->fresh()?->status)->toBe(VersionStatus::FetchFailed)
        ->and($version->fresh()?->status_seen_at)->toBeNull();
});

it('a nova importação pedida pelo admin não aparece como novidade', function (): void {
    $game = Game::factory()->create();
    GameVersion::factory()->for($game)->fetchFailed()->create();

    $version = app(RequestNewImport::class)(User::factory()->admin()->create(), $game->id);

    expect($version->fresh()?->status_seen_at)->not->toBeNull();
});

it('a lista indica as versões novas', function (): void {
    $new = GameVersion::factory()->create();
    $old = seen(GameVersion::factory()->create());

    $response = $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/admin/versions?status=import_requested')->assertOk();

    expect(collect($response->json('data'))->pluck('is_new', 'id')->all())->toBe([$new->id => true, $old->id => false]);
});

it('marca como vistas só as versões ainda no estado exibido, sem auditoria', function (): void {
    $shown = GameVersion::factory()->create();
    $moved = GameVersion::factory()->fetchFailed()->create(); // exibida como import_requested, mas já mudou

    $this->actingAs(User::factory()->admin()->create())->postJson('/api/v1/admin/versions/seen', ['versions' => [
        ['id' => strtoupper($shown->id), 'status' => 'import_requested'],
        ['id' => $moved->id, 'status' => 'import_requested'],
    ]])->assertNoContent();

    expect($shown->fresh()?->status_seen_at)->not->toBeNull()
        ->and($moved->fresh()?->status_seen_at)->toBeNull()
        ->and(AuditEntry::count())->toBe(0);
});

it('valida o pedido de marcar como vistas', function (array $payload): void {
    $this->actingAs(User::factory()->admin()->create())->postJson('/api/v1/admin/versions/seen', $payload)->assertUnprocessable();
})->with([
    'vazio' => [['versions' => []]],
    'id inválido' => [['versions' => [['id' => '../x', 'status' => 'import_requested']]]],
    'estado inválido' => [['versions' => [['id' => '01JAAAAAAAAAAAAAAAAAAAAAAA', 'status' => 'hackeado']]]],
    'mais de uma página' => [['versions' => array_fill(0, 26, ['id' => '01JAAAAAAAAAAAAAAAAAAAAAAA', 'status' => 'import_requested'])]],
]);

it('um developer não vê os contadores nem marca versões', function (): void {
    $developer = User::factory()->developer()->create();

    $this->actingAs($developer)->getJson('/api/v1/admin/versions/summary')->assertForbidden();
    $this->actingAs($developer)->postJson('/api/v1/admin/versions/seen', ['versions' => [['id' => '01JAAAAAAAAAAAAAAAAAAAAAAA', 'status' => 'import_requested']]])->assertForbidden();
});
