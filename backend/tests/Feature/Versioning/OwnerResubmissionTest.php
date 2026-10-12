<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\RejectImport;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\Exceptions\InvalidVersionTransition;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
| O dono acompanha onde a versão parou e por quê, e reenvia: a nova versão volta ao Portão 0.
*/

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
});

describe('acompanhamento', function (): void {
    it('lista só a versão mais recente de cada jogo do dono', function (): void {
        $owner = User::factory()->developer()->create();
        $game = Game::factory()->for($owner, 'owner')->create();
        GameVersion::factory()->for($game)->fetchFailed()->create();
        $latest = GameVersion::factory()->for($game)->create(['number' => 2]);
        GameVersion::factory()->create(); // jogo de outro dono

        $this->actingAs($owner)->getJson('/api/v1/me/versions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $latest->id)
            ->assertJsonPath('data.0.game_id', $game->id)
            ->assertJsonPath('data.0.status', 'import_requested')
            ->assertJsonPath('data.0.in_progress', true);
    });

    it('mostra as violações da versão reprovada na validação', function (): void {
        $owner = User::factory()->developer()->create();
        GameVersion::factory()->for(Game::factory()->for($owner, 'owner'))->validationFailed()->create();

        $response = $this->actingAs($owner)->getJson('/api/v1/me/versions')
            ->assertJsonPath('data.0.status', 'validation_failed')
            ->assertJsonPath('data.0.in_progress', false)
            ->assertJsonMissingPath('data.0.ignored');
        // jsonb não preserva a ordem das chaves.
        expect($response->json('data.0.violations'))->toEqual([[
            'code' => 'required_file_missing',
            'message' => 'O arquivo obrigatório game.js não existe na raiz do repositório.',
            'path' => 'game.js',
        ]]);
    });

    it('mostra o motivo da rejeição do Portão 0, sem dizer quem decidiu', function (): void {
        $owner = User::factory()->developer()->create();
        $version = GameVersion::factory()->for(Game::factory()->for($owner, 'owner'))->create();
        app(RejectImport::class)(User::factory()->admin()->create(), $version->id, DecisionReason::fromString('Repositório de outra pessoa.'));

        $this->actingAs($owner)->getJson('/api/v1/me/versions')
            ->assertJsonPath('data.0.status', 'import_rejected')
            ->assertJsonPath('data.0.rejection_reason', 'Repositório de outra pessoa.')
            ->assertJsonMissingPath('data.0.import_decision');
    });

    it('mostra a falha da busca com a mensagem', function (): void {
        $owner = User::factory()->developer()->create();
        GameVersion::factory()->for(Game::factory()->for($owner, 'owner'))->fetchFailed()->create();

        $this->actingAs($owner)->getJson('/api/v1/me/versions')
            ->assertJsonPath('data.0.fetch_failure', ['code' => 'ref_not_found', 'message' => 'A branch ou tag não existe no repositório.']);
    });

    it('exige sessão', function (): void {
        $this->getJson('/api/v1/me/versions')->assertUnauthorized();
    });
});

describe('reenvio pelo dono', function (): void {
    it('cria a versão seguinte, de volta ao Portão 0, auditada e sem acessar a rede (I2)', function (string $state): void {
        $owner = User::factory()->developer()->create();
        $game = Game::factory()->for($owner, 'owner')->create(['default_ref' => 'develop']);
        GameVersion::factory()->for($game)->{$state}()->create();

        $this->actingAs($owner)->postJson("/api/v1/me/games/{$game->id}/versions")
            ->assertCreated()
            ->assertJsonPath('data.number', 2)
            ->assertJsonPath('data.status', 'import_requested')
            ->assertJsonPath('data.requested_ref', 'develop');

        $entry = AuditEntry::sole();
        expect($entry->action)->toBe('versioning.import-requested')->and($entry->actor_id)->toBe($owner->id);
        Queue::assertNothingPushed();
        Http::assertNothingSent();

        // E aparece para o admin na fila do Portão 0.
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/v1/admin/versions?status=import_requested')
            ->assertJsonPath('data.0.game.id', $game->id);
    })->with(['validationFailed', 'fetchFailed', 'published']);

    it('recusa enquanto houver versão em andamento', function (): void {
        $owner = User::factory()->developer()->create();
        $game = Game::factory()->for($owner, 'owner')->create();
        GameVersion::factory()->for($game)->fetching()->create();

        $this->actingAs($owner)->postJson("/api/v1/me/games/{$game->id}/versions")->assertUnprocessable();
        expect(GameVersion::count())->toBe(1);
    });

    it('recusa jogo bloqueado', function (): void {
        $owner = User::factory()->developer()->create();
        $game = Game::factory()->for($owner, 'owner')->create(['status' => GameStatus::Blocked]);
        GameVersion::factory()->for($game)->validationFailed()->create();

        $this->actingAs($owner)->postJson("/api/v1/me/games/{$game->id}/versions")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este jogo está bloqueado. Novas versões só depois do desbloqueio.');
    });

    it('outro developer não reenvia o jogo de alguém', function (): void {
        $game = Game::factory()->create();
        GameVersion::factory()->for($game)->validationFailed()->create();

        $this->actingAs(User::factory()->developer()->create())->postJson("/api/v1/me/games/{$game->id}/versions")->assertForbidden();
        expect(GameVersion::count())->toBe(1);
    });

    it('o dono sem a permissão de submeter não reenvia', function (): void {
        $owner = User::factory()->create(); // sem a role developer
        $game = Game::factory()->for($owner, 'owner')->create();
        GameVersion::factory()->for($game)->validationFailed()->create();

        $this->actingAs($owner)->postJson("/api/v1/me/games/{$game->id}/versions")->assertForbidden();
    });

    it('responde 404 para jogo inexistente', function (): void {
        $this->actingAs(User::factory()->developer()->create())
            ->postJson('/api/v1/me/games/01JAAAAAAAAAAAAAAAAAAAAAAA/versions')
            ->assertNotFound();
    });
});

it('estado terminal: a versão reprovada nunca volta', function (): void {
    $version = GameVersion::factory()->validationFailed()->create();

    expect($version->status)->toBe(VersionStatus::ValidationFailed)
        ->and(fn () => $version->authorizeImport(User::factory()->admin()->create()))->toThrow(InvalidVersionTransition::class);
});
