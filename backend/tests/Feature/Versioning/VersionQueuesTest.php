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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| Filas do admin: contadores por estado e a tag "Novo" (nenhum admin viu a versão no estado atual).
*/

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('quarantine'); // a falha da busca limpa a quarentena
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

    expect(collect($response->json('data'))->pluck('is_new', 'id')->all())->toEqual([$new->id => true, $old->id => false]); // mesma hora de criação: a ordem não importa aqui
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

it('conta as versões prontas para publicar: em revisão de arquivos com todos eles aprovados', function (): void {
    $withFiles = function (GameVersion $version, array $statuses): GameVersion {
        foreach ($statuses as $i => $status) {
            DB::table('version_files')->insert([
                'id' => (string) Str::ulid(), 'game_version_id' => $version->id, 'path' => "f{$i}.js",
                'sha256' => str_repeat('a', 64), 'size' => 1, 'git_blob_sha' => str_repeat('b', 40),
                'change_type' => 'added', 'review_status' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $version;
    };
    $withFiles(GameVersion::factory()->awaitingFileReview()->create(), ['approved', 'approved']);   // pronta
    $withFiles(GameVersion::factory()->awaitingFileReview()->create(), ['approved', 'pending']);    // falta arquivo
    $withFiles(GameVersion::factory()->awaitingFileReview()->create(), []);                         // sem arquivos
    $withFiles(GameVersion::factory()->published()->create(), ['approved']);                        // já publicada

    $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/admin/versions/summary')
        ->assertOk()
        ->assertJsonPath('meta.ready_to_publish', 1)
        ->assertJsonPath('data.awaiting_file_review.total', 3);
});

describe('filtros das filas (Fase 7)', function (): void {
    it('busca pelo nome ou slug do jogo e pelo nome de quem pediu, sem tratar % e _ como curingas', function (): void {
        $admin = User::factory()->admin()->create();
        $snake = GameVersion::factory()->for(Game::factory()->create(['name' => 'Snake', 'slug' => 'snake']))->create();
        $pong = GameVersion::factory()->for(Game::factory()->create(['name' => 'Pong', 'slug' => 'pong-100']))->create();
        $byAda = GameVersion::factory()->for(Game::factory()->create(['name' => 'Tetris', 'slug' => 'tetris']))
            ->create(['requested_by_id' => User::factory()->developer()->create(['name' => 'Ada Lovelace'])->id]);

        $ids = fn (string $search): array => collect($this->actingAs($admin)->getJson('/api/v1/admin/versions?status=import_requested&search='.urlencode($search))->assertOk()->json('data'))->pluck('id')->all();

        expect($ids('SNA'))->toBe([$snake->id])
            ->and($ids('pong'))->toBe([$pong->id])
            ->and($ids('lovelace'))->toBe([$byAda->id])
            ->and($ids('%'))->toBe([])
            ->and($ids('p_ng'))->toBe([]);
    });

    it('lista só as prontas para publicar com ready=1', function (): void {
        $admin = User::factory()->admin()->create();
        $row = fn (GameVersion $v, string $status) => DB::table('version_files')->insert([
            'id' => (string) Str::ulid(), 'game_version_id' => $v->id, 'path' => 'index.html', 'sha256' => str_repeat('a', 64), 'size' => 1,
            'git_blob_sha' => str_repeat('b', 40), 'change_type' => 'added', 'review_status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ready = GameVersion::factory()->awaitingFileReview()->create();
        $row($ready, 'approved');
        $row(GameVersion::factory()->awaitingFileReview()->create(), 'pending');

        $this->actingAs($admin)->getJson('/api/v1/admin/versions?status=awaiting_file_review&ready=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ready->id);
    });

    it('valida os filtros', function (): void {
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/admin/versions?search='.str_repeat('a', 101).'&ready=talvez')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search', 'ready']);
    });
});
