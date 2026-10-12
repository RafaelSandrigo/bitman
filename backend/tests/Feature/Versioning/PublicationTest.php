<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Catalog\Application\Actions\BlockGame;
use App\Modules\Catalog\Application\Actions\UnblockGame;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\PublishVersion;
use App\Modules\Versioning\Application\Actions\ReviewFile;
use App\Modules\Versioning\Application\Actions\WithdrawVersion;
use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\ChangeType;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\FileDecision;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantinedFile;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Artifacts;
use Tests\Support\Snapshots;

/*
| Publicação, retirada e bloqueio (I6–I9, J6, P1, P2) com o volume de artefatos num disco real.
*/

beforeEach(function (): void {
    Storage::fake('quarantine');
    $this->root = Artifacts::temporaryRoot();
    $this->admin = User::factory()->admin()->create(['name' => 'Rafael']);
});

afterEach(function (): void {
    Artifacts::remove($this->root);
});

/** Versão em awaiting_file_review com os 3 arquivos na quarentena, aprovados (ou não). */
function readyVersion(User $admin, ?Game $game = null, array $contents = [], bool $approve = true): GameVersion
{
    $game ??= Game::factory()->create();
    $number = (int) GameVersion::query()->where('game_id', $game->id)->max('number') + 1;
    $version = GameVersion::factory()->for($game)->awaitingFileReview()->create(['number' => $number]);
    foreach ([...Snapshots::gameFiles(), ...$contents] as $path => $content) {
        $sha256 = app(QuarantineStore::class)->put($version->id, $content);
        $file = VersionFile::record($version, new QuarantinedFile($path, $sha256, strlen($content), sha1($content), ChangeType::Added));
        if ($approve) {
            app(ReviewFile::class)($admin, $version->id, $file->id, $sha256, FileDecision::Approved, null);
        }
    }

    return $version->fresh() ?? throw new RuntimeException('versão sumiu');
}

function served(string $root, ?string $publicId): string
{
    return "{$root}/served/{$publicId}";
}

describe('publicação (I6–I8)', function (): void {
    it('publica: archive write-once, diretório servido legível pelo nginx, jogo no ar e auditoria com os hashes', function (): void {
        $version = readyVersion($this->admin, contents: ['game.js' => "console.log('oi')\n"]);

        $this->actingAs($this->admin)->postJson("/api/v1/admin/versions/{$version->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $version->refresh();
        $game = $version->game->fresh();
        $dir = served($this->root, $version->public_id);
        expect($version->public_id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
            ->and($version->published_by_id)->toBe($this->admin->id)
            ->and(file_get_contents("{$dir}/game.js"))->toBe("console.log('oi')\n")
            ->and(Artifacts::mode($dir))->toBe('0755')
            ->and(Artifacts::mode("{$dir}/index.html"))->toBe('0644')
            ->and(file_get_contents("{$this->root}/archive/{$version->game_id}/v1/game.js"))->toBe("console.log('oi')\n")
            ->and($game?->status)->toBe(GameStatus::Published)
            ->and($game?->published_version_id)->toBe($version->id)
            ->and($game?->first_published_at)->not->toBeNull()
            // O conteúdo está no archive: a quarentena foi descartada.
            ->and(Storage::disk('quarantine')->allFiles())->toBe([]);

        $entry = AuditEntry::query()->where('action', 'versioning.version-published')->sole();
        expect($entry->actor_id)->toBe($this->admin->id)
            ->and($entry->context)->toMatchArray([
                'public_id' => $version->public_id,
                'commit' => $version->commit_sha,
                'game.js' => hash('sha256', "console.log('oi')\n"),
            ]);
    });

    it('exige todos os arquivos aprovados e não grava nada sem isso (I6)', function (): void {
        $version = readyVersion($this->admin, approve: false);

        $this->actingAs($this->admin)->postJson("/api/v1/admin/versions/{$version->id}/publish")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A versão só pode ser publicada com todos os arquivos aprovados.');

        expect($version->fresh()?->status)->toBe(VersionStatus::AwaitingFileReview)
            ->and(file_exists("{$this->root}/archive"))->toBeFalse()
            ->and(file_exists("{$this->root}/served"))->toBeFalse();
    });

    it('recalcula o hash da quarentena: conteúdo trocado depois da aprovação nunca é publicado (I6, P1)', function (): void {
        $version = readyVersion($this->admin);
        $file = $version->files()->where('path', 'game.js')->sole();
        Storage::disk('quarantine')->put(strtolower($version->id).'/'.$file->sha256, 'minerador()');

        $this->actingAs($this->admin)->postJson("/api/v1/admin/versions/{$version->id}/publish")->assertStatus(409);

        expect($version->fresh()?->status)->toBe(VersionStatus::AwaitingFileReview)
            ->and(file_exists("{$this->root}/archive"))->toBeFalse()
            ->and(glob("{$this->root}/served/*") ?: [])->toBe([]);
    });

    it('nunca sobrescreve o archive (I7, P2): a publicação falha e não deixa nada servido', function (): void {
        $version = readyVersion($this->admin);
        app(ArtifactStore::class)->archive($version->game_id, 1, ['game.js' => 'conteúdo anterior']);

        $this->actingAs($this->admin)->postJson("/api/v1/admin/versions/{$version->id}/publish")->assertStatus(409);

        expect($version->fresh()?->status)->toBe(VersionStatus::AwaitingFileReview)
            ->and(file_get_contents("{$this->root}/archive/{$version->game_id}/v1/game.js"))->toBe('conteúdo anterior')
            ->and(glob("{$this->root}/served/*") ?: [])->toBe([]);
    });

    it('falha depois de gravar: desfaz o archive e o diretório servido desta tentativa', function (): void {
        $version = readyVersion($this->admin);
        GameVersion::saving(function (GameVersion $v): void {
            if ($v->status === VersionStatus::Published) {
                throw new RuntimeException('banco fora do ar');
            }
        });

        expect(fn () => app(PublishVersion::class)($this->admin, $version->id))->toThrow(RuntimeException::class, 'banco fora do ar');

        expect(glob("{$this->root}/archive/*/*") ?: [])->toBe([])
            ->and(glob("{$this->root}/served/*") ?: [])->toBe([]);
        // E dá para tentar de novo.
        GameVersion::flushEventListeners();
    });

    it('publicar a v2 tira a v1 do ar e a marca como superseded na mesma transação (I8)', function (): void {
        $game = Game::factory()->create();
        $v1 = readyVersion($this->admin, $game);
        app(PublishVersion::class)($this->admin, $v1->id);
        $v1->refresh();
        $v2 = readyVersion($this->admin, $game, ['style.css' => 'body { color: red }']);

        app(PublishVersion::class)($this->admin, $v2->id);

        $v2->refresh();
        expect($v1->fresh()?->status)->toBe(VersionStatus::Superseded)
            ->and(is_dir(served($this->root, $v1->public_id)))->toBeFalse()
            ->and(is_dir(served($this->root, $v2->public_id)))->toBeTrue()
            ->and($game->fresh()?->published_version_id)->toBe($v2->id)
            // O archive é permanente.
            ->and(is_dir("{$this->root}/archive/{$game->id}/v1"))->toBeTrue()
            ->and(AuditEntry::query()->where('action', 'versioning.version-superseded')->sole()->subject_id)->toBe($v1->id);
    });

    it('o banco garante no máximo uma versão publicada por jogo (I8)', function (): void {
        $game = Game::factory()->published()->create();
        GameVersion::factory()->for($game)->published()->create();

        expect(fn () => GameVersion::factory()->for($game)->published()->create(['number' => 2]))->toThrow(QueryException::class);
    });

    it('não publica versão de jogo bloqueado (J6)', function (): void {
        $game = Game::factory()->blocked()->create();
        $version = readyVersion($this->admin, $game);

        $this->actingAs($this->admin)->postJson("/api/v1/admin/versions/{$version->id}/publish")->assertUnprocessable();

        expect(glob("{$this->root}/served/*") ?: [])->toBe([]);
    });
});

describe('retirada e bloqueio (J6, I9)', function (): void {
    beforeEach(function (): void {
        $this->version = readyVersion($this->admin);
        app(PublishVersion::class)($this->admin, $this->version->id);
        $this->version->refresh();
        $this->game = $this->version->game->fresh();
    });

    it('retirar exige motivo, tira do ar e tira o jogo do catálogo; o archive fica', function (): void {
        $this->actingAs($this->admin)->postJson("/api/v1/admin/versions/{$this->version->id}/withdraw", ['reason' => ''])->assertUnprocessable();

        $this->actingAs($this->admin)->postJson("/api/v1/admin/versions/{$this->version->id}/withdraw", ['reason' => 'Bug que trava o navegador.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');

        expect(is_dir(served($this->root, $this->version->public_id)))->toBeFalse()
            ->and(is_dir("{$this->root}/archive/{$this->game->id}/v1"))->toBeTrue()
            ->and($this->game->fresh()?->published_version_id)->toBeNull()
            ->and(AuditEntry::query()->where('action', 'versioning.version-withdrawn')->sole()->reason)->toBe('Bug que trava o navegador.');
        $this->getJson('/api/v1/games')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/games/{$this->game->slug}")->assertNotFound();
    });

    it('bloquear tira do ar na hora; desbloquear volta a servir a partir do archive', function (): void {
        $this->actingAs($this->admin)->postJson("/api/v1/admin/games/{$this->game->id}/block", ['reason' => 'Minerador escondido.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('data.block_reason', 'Minerador escondido.');

        expect(is_dir(served($this->root, $this->version->public_id)))->toBeFalse()
            ->and(AuditEntry::query()->where('action', 'catalog.game-blocked')->sole()->reason)->toBe('Minerador escondido.');
        $this->getJson("/api/v1/games/{$this->game->slug}")->assertNotFound();
        $this->actingAs($this->admin)->getJson("/api/v1/admin/games/{$this->game->id}")->assertJsonPath('meta.is_served', false);

        $this->actingAs($this->admin)->postJson("/api/v1/admin/games/{$this->game->id}/unblock")->assertOk()->assertJsonPath('data.status', 'published');

        expect(file_get_contents(served($this->root, $this->version->public_id).'/game.js'))->toBe(Snapshots::gameFiles()['game.js']);
        $this->getJson("/api/v1/games/{$this->game->slug}")->assertOk();
        $this->actingAs($this->admin)->getJson("/api/v1/admin/games/{$this->game->id}")->assertJsonPath('meta.is_served', true);
    });

    it('archive adulterado nunca volta ao ar no desbloqueio', function (): void {
        app(BlockGame::class)($this->admin, $this->game->id, DecisionReason::fromString('Revisão.'));
        $archived = "{$this->root}/archive/{$this->game->id}/v1/game.js";
        chmod($archived, 0600);
        file_put_contents($archived, 'minerador()');

        app(UnblockGame::class)($this->admin, $this->game->id);

        expect(is_dir(served($this->root, $this->version->public_id)))->toBeFalse();
    });

    it('só jogo publicado pode ser bloqueado, e só bloqueado desbloqueado', function (): void {
        $submitted = Game::factory()->create();

        $this->actingAs($this->admin)->postJson("/api/v1/admin/games/{$submitted->id}/block", ['reason' => 'Motivo.'])->assertUnprocessable();
        $this->actingAs($this->admin)->postJson("/api/v1/admin/games/{$this->game->id}/unblock")->assertUnprocessable();
    });

    it('o dono não publica, não retira, não bloqueia nem desbloqueia (I1)', function (): void {
        $owner = $this->game->owner;
        $reason = DecisionReason::fromString('Quero.');

        expect(fn () => app(PublishVersion::class)($owner, $this->version->id))->toThrow(AuthorizationException::class)
            ->and(fn () => app(WithdrawVersion::class)($owner, $this->version->id, $reason))->toThrow(AuthorizationException::class)
            ->and(fn () => app(BlockGame::class)($owner, $this->game->id, $reason))->toThrow(AuthorizationException::class)
            ->and(fn () => app(UnblockGame::class)($owner, $this->game->id))->toThrow(AuthorizationException::class);
    });
});

describe('catálogo público (I9)', function (): void {
    it('lista só jogos publicados e não bloqueados, sem dados internos', function (): void {
        $version = readyVersion($this->admin);
        app(PublishVersion::class)($this->admin, $version->id);
        $version->refresh();
        Game::factory()->create(); // submetido
        $blocked = readyVersion($this->admin);
        app(PublishVersion::class)($this->admin, $blocked->id);
        app(BlockGame::class)($this->admin, $blocked->game_id, DecisionReason::fromString('Bloqueado.'));

        $response = $this->getJson('/api/v1/games')->assertOk()->assertJsonCount(1, 'data');

        expect($response->json('data.0'))->toMatchArray([
            'slug' => $version->game->slug,
            'version_number' => 1,
            'play_url' => "https://bitman-games.sandrigo.com.br/{$version->public_id}/index.html",
        ])->and($response->json('data.0'))->not->toHaveKeys(['owner', 'repository_url', 'status', 'id']);
    });

    it('detalhe pelo slug; jogo não publicado responde como inexistente', function (): void {
        $version = readyVersion($this->admin);
        app(PublishVersion::class)($this->admin, $version->id);
        $submitted = Game::factory()->create();

        $this->getJson("/api/v1/games/{$version->game->slug}")->assertOk()->assertJsonPath('data.name', $version->game->name);
        $this->getJson("/api/v1/games/{$submitted->slug}")->assertNotFound();
        $this->getJson('/api/v1/games/..%2F..%2Fetc')->assertNotFound();
    });
});

it('histórico de versões do jogo no admin, da mais recente para a mais antiga', function (): void {
    $game = Game::factory()->create();
    GameVersion::factory()->for($game)->fetchFailed()->create();
    GameVersion::factory()->for($game)->validationFailed()->create(['number' => 2]);
    GameVersion::factory()->create(); // outro jogo

    $this->actingAs($this->admin)->getJson("/api/v1/admin/versions?game_id={$game->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.number', 2)
        ->assertJsonPath('data.1.number', 1);
});
