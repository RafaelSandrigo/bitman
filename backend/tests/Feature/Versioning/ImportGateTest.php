<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\AuthorizeImport;
use App\Modules\Versioning\Application\Actions\RejectImport;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use App\Modules\Versioning\Infrastructure\Jobs\FetchRepositoryJob;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
| Portão 0 — autorização Git (docs/dominio.md, I1, I2, I11, I12).
*/

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
});

it('o admin autoriza a importação: a versão vai para a busca, auditada, e o job entra na fila ingestion', function (): void {
    $admin = User::factory()->admin()->create();
    $version = GameVersion::factory()->create();

    $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$version->id}/authorize-import")
        ->assertOk()
        ->assertJsonPath('data.status', 'fetching');

    $version->refresh();
    $entry = AuditEntry::sole();
    expect($version->status)->toBe(VersionStatus::Fetching)
        ->and($version->import_decided_by_id)->toBe($admin->id)
        ->and($version->import_decided_at)->not->toBeNull()
        ->and($entry->action)->toBe('versioning.import-authorized')
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->subject_id)->toBe($version->id)
        ->and($entry->context)->toMatchArray(['number' => 1, 'ref' => 'main', 'repository_url' => $version->repository_url]);

    Queue::assertPushedOn('ingestion', FetchRepositoryJob::class, fn (FetchRepositoryJob $job) => $job->versionId === $version->id);
});

it('o admin rejeita a importação com motivo: nenhum acesso ao repositório (I2)', function (): void {
    $admin = User::factory()->admin()->create();
    $version = GameVersion::factory()->create();

    $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$version->id}/reject-import", ['reason' => '  Repositório não parece ser um jogo.  '])
        ->assertOk()
        ->assertJsonPath('data.status', 'import_rejected');

    $entry = AuditEntry::sole();
    expect($version->fresh()?->import_rejection_reason)->toBe('Repositório não parece ser um jogo.')
        ->and($entry->action)->toBe('versioning.import-rejected')
        ->and($entry->reason)->toBe('Repositório não parece ser um jogo.');

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('a rejeição exige motivo (I11)', function (mixed $reason): void {
    $admin = User::factory()->admin()->create();
    $version = GameVersion::factory()->create();

    $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$version->id}/reject-import", ['reason' => $reason])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    expect($version->fresh()?->status)->toBe(VersionStatus::ImportRequested)->and(AuditEntry::count())->toBe(0);
})->with([null, '', '  ', 'ok', 123]);

it('a decisão do Portão 0 só vale para versão aguardando autorização', function (string $state, string $endpoint, array $payload): void {
    $admin = User::factory()->admin()->create();
    $version = GameVersion::factory()->{$state}()->create();
    $before = $version->status;

    $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$version->id}/{$endpoint}", $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'A versão está '));

    expect($version->fresh()?->status)->toBe($before)->and(AuditEntry::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'autorizar de novo' => ['fetching', 'authorize-import', []],
    'autorizar depois da busca' => ['awaitingCommitReview', 'authorize-import', []],
    'rejeitar em busca' => ['fetching', 'reject-import', ['reason' => 'Motivo qualquer']],
    'autorizar busca que falhou' => ['fetchFailed', 'authorize-import', []],
]);

it('responde 404 para versão inexistente', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson('/api/v1/admin/versions/01JAAAAAAAAAAAAAAAAAAAAAAA/authorize-import')->assertNotFound();
});

it('as Actions do Portão 0 recusam quem não é admin, inclusive o dono do jogo (I12)', function (): void {
    $owner = User::factory()->developer()->create();
    $game = Game::factory()->for($owner, 'owner')->create();
    $version = GameVersion::factory()->for($game)->create();

    expect(fn () => app(AuthorizeImport::class)($owner, $version->id))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RejectImport::class)($owner, $version->id, DecisionReason::fromString('Não quero')))->toThrow(AuthorizationException::class)
        ->and($version->fresh()?->status)->toBe(VersionStatus::ImportRequested);
});

describe('nova importação', function (): void {
    it('cria a versão seguinte depois de uma busca que falhou, sem acessar a rede', function (): void {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create(['default_ref' => 'develop']);
        GameVersion::factory()->for($game)->fetchFailed()->create();

        $this->actingAs($admin)->postJson("/api/v1/admin/games/{$game->id}/versions")
            ->assertCreated()
            ->assertJsonPath('data.number', 2)
            ->assertJsonPath('data.status', 'import_requested')
            ->assertJsonPath('data.requested_ref', 'develop');

        $entry = AuditEntry::sole();
        expect($entry->action)->toBe('versioning.import-requested')->and($entry->actor_id)->toBe($admin->id);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    });

    it('recusa enquanto houver versão em andamento', function (string $state): void {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        GameVersion::factory()->for($game)->{$state}()->create();

        $this->actingAs($admin)->postJson("/api/v1/admin/games/{$game->id}/versions")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este jogo já tem uma versão em andamento. Conclua ou rejeite essa versão antes de pedir outra importação.');

        expect(GameVersion::count())->toBe(1);
    })->with(['fetching', 'awaitingCommitReview']);

    it('o banco garante no máximo uma versão em andamento por jogo', function (): void {
        $game = Game::factory()->create();
        GameVersion::factory()->for($game)->create();

        expect(fn () => GameVersion::factory()->for($game)->create(['number' => 2]))->toThrow(UniqueConstraintViolationException::class);
    });

    it('responde 404 para jogo inexistente', function (): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/games/01JAAAAAAAAAAAAAAAAAAAAAAA/versions')->assertNotFound();
    });
});

describe('consulta', function (): void {
    it('lista a fila do Portão 0 em ordem de chegada', function (): void {
        $admin = User::factory()->admin()->create();
        $older = GameVersion::factory()->create(['created_at' => now()->subDay()]);
        $newer = GameVersion::factory()->create(['created_at' => now()->subHour()]);
        GameVersion::factory()->fetching()->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/versions?status=import_requested')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('data.1.id', $newer->id)
            ->assertJsonPath('data.0.game.name', $older->game->name)
            ->assertJsonPath('data.0.requested_by.id', $older->requested_by_id)
            ->assertJsonPath('meta.total', 2);
    });

    it('recusa estado desconhecido no filtro', function (): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/versions?status=hackeado')->assertUnprocessable()->assertJsonValidationErrors(['status']);
    });

    it('mostra a falha da busca com a mensagem para o admin', function (): void {
        $admin = User::factory()->admin()->create();
        $version = GameVersion::factory()->fetchFailed()->create();

        $this->actingAs($admin)->getJson("/api/v1/admin/versions/{$version->id}")
            ->assertOk()
            ->assertJsonPath('data.fetch_failure', ['code' => 'ref_not_found', 'message' => 'A branch ou tag não existe no repositório.'])
            ->assertJsonPath('data.import_decision.decided_by.id', $version->import_decided_by_id)
            ->assertJsonPath('data.files', []);
    });
});
