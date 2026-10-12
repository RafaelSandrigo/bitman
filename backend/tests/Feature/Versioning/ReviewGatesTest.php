<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\ApproveCommit;
use App\Modules\Versioning\Application\Actions\RejectCommit;
use App\Modules\Versioning\Application\Actions\RejectVersion;
use App\Modules\Versioning\Application\Actions\ReviewFile;
use App\Modules\Versioning\Application\Actions\ShowFileContent;
use App\Modules\Versioning\Domain\ChangeType;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\Exceptions\InvalidDecisionReason;
use App\Modules\Versioning\Domain\FileDecision;
use App\Modules\Versioning\Domain\FileReview;
use App\Modules\Versioning\Domain\FileReviewStatus;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantinedFile;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Snapshots;

/*
| Portão 1 (commit) e Portão 2 (arquivos) — docs/dominio.md, I1, I3, I4, I5, I11, I13, J5, P1, ADR 0006.
*/

beforeEach(function (): void {
    Storage::fake('quarantine');
});

/** Versão no estado pedido, com os 3 arquivos do jogo na quarentena. */
function versionWithFiles(string $state = 'awaitingFileReview', array $contents = [], ?Game $game = null): GameVersion
{
    $factory = GameVersion::factory()->{$state}();
    $version = ($game === null ? $factory : $factory->for($game))->create($game === null ? [] : ['number' => GameVersion::query()->where('game_id', $game->id)->count() + 1]);
    foreach ([...Snapshots::gameFiles(), ...$contents] as $path => $content) {
        $sha256 = app(QuarantineStore::class)->put($version->id, $content);
        VersionFile::record($version, new QuarantinedFile($path, $sha256, strlen($content), sha1($content), ChangeType::Added));
    }

    return $version->fresh() ?? throw new RuntimeException('versão sumiu');
}

function fileOf(GameVersion $version, string $path): VersionFile
{
    return $version->files()->where('path', $path)->sole();
}

function reviewUrl(GameVersion $version, VersionFile $file): string
{
    return "/api/v1/admin/versions/{$version->id}/files/{$file->id}/review";
}

describe('Portão 1 — commit', function (): void {
    it('o admin aprova o commit: a versão vai para a revisão dos arquivos, auditada com o commit (I11)', function (): void {
        $admin = User::factory()->admin()->create();
        $version = versionWithFiles('awaitingCommitReview');

        $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$version->id}/approve-commit")
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_file_review');

        $version->refresh();
        $entry = AuditEntry::sole();
        expect($version->status)->toBe(VersionStatus::AwaitingFileReview)
            ->and($version->commit_decided_by_id)->toBe($admin->id)
            ->and($version->commit_decided_at)->not->toBeNull()
            ->and($entry->action)->toBe('versioning.commit-approved')
            ->and($entry->actor_id)->toBe($admin->id)
            ->and($entry->context)->toMatchArray(['commit' => $version->commit_sha]);
    });

    it('nunca aprova um relatório com violação bloqueante (I3, defesa em profundidade)', function (): void {
        $admin = User::factory()->admin()->create();
        // Só montável por fora do agregado: o fetch nunca leva relatório bloqueado ao Portão 1.
        $version = GameVersion::factory()->awaitingCommitReview()->create([
            'validation_report' => ['blocked' => true, 'violations' => [['code' => 'symlink', 'message' => 'x', 'path' => 'a']], 'ignored' => [], 'ignored_total' => 0],
        ]);

        $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$version->id}/approve-commit")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O relatório tem violações bloqueantes: o commit não pode ser aprovado.');

        expect($version->fresh()?->status)->toBe(VersionStatus::AwaitingCommitReview)->and(AuditEntry::count())->toBe(0);
    });

    it('o admin rejeita o commit com motivo: auditado e a quarentena é descartada', function (): void {
        $admin = User::factory()->admin()->create();
        $version = versionWithFiles('awaitingCommitReview');

        $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$version->id}/reject-commit", ['reason' => 'Commit com código de terceiros.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'commit_rejected');

        $entry = AuditEntry::sole();
        expect($version->fresh()?->commit_rejection_reason)->toBe('Commit com código de terceiros.')
            ->and($entry->action)->toBe('versioning.commit-rejected')
            ->and($entry->reason)->toBe('Commit com código de terceiros.')
            ->and(Storage::disk('quarantine')->allFiles())->toBe([]);
    });

    it('a rejeição do commit exige motivo (I11)', function (): void {
        $version = versionWithFiles('awaitingCommitReview');

        $this->actingAs(User::factory()->admin()->create())->postJson("/api/v1/admin/versions/{$version->id}/reject-commit", ['reason' => ' '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });

    it('a decisão do Portão 1 só vale para versão aguardando a revisão do commit', function (string $state, string $endpoint): void {
        $version = GameVersion::factory()->{$state}()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/v1/admin/versions/{$version->id}/{$endpoint}", ['reason' => 'Motivo qualquer.'])
            ->assertUnprocessable();
    })->with([
        ['fetching', 'approve-commit'],
        ['validationFailed', 'approve-commit'],
        ['awaitingFileReview', 'approve-commit'],
        ['awaitingFileReview', 'reject-commit'],
    ]);
});

describe('Portão 2 — conteúdo (J5, P1, I13)', function (): void {
    it('devolve o conteúdo exato da quarentena, com o hash, sem cache', function (): void {
        $version = versionWithFiles(contents: ['game.js' => "alert('<script>oi</script>')\n"]);
        $file = fileOf($version, 'game.js');

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/v1/admin/versions/{$version->id}/files/{$file->id}/content")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.path', 'game.js')
            ->assertJsonPath('data.sha256', $file->sha256)
            ->assertJsonPath('data.content', "alert('<script>oi</script>')\n");
    });

    it('também na revisão do commit', function (): void {
        $version = versionWithFiles('awaitingCommitReview');
        $file = fileOf($version, 'index.html');

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/v1/admin/versions/{$version->id}/files/{$file->id}/content")
            ->assertOk();
    });

    it('quarentena adulterada nunca é mostrada (P1)', function (): void {
        $version = versionWithFiles();
        $file = fileOf($version, 'game.js');
        Storage::disk('quarantine')->put(strtolower($version->id).'/'.$file->sha256, 'conteúdo trocado');

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/v1/admin/versions/{$version->id}/files/{$file->id}/content")
            ->assertStatus(409)
            ->assertJsonMissingPath('data');
    });

    it('arquivo de outra versão responde 404', function (): void {
        $version = versionWithFiles();
        $other = fileOf(versionWithFiles(), 'game.js');

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/v1/admin/versions/{$version->id}/files/{$other->id}/content")
            ->assertNotFound();
    });

    it('fora da revisão o conteúdo não está disponível', function (): void {
        $version = versionWithFiles();
        $file = fileOf($version, 'game.js');
        app(RejectVersion::class)(User::factory()->admin()->create(), $version->id, DecisionReason::fromString('Não serve.'));

        $this->actingAs(User::factory()->admin()->create())
            ->getJson("/api/v1/admin/versions/{$version->id}/files/{$file->id}/content")
            ->assertUnprocessable();
    });
});

describe('Portão 2 — arquivos (I4, I5, I11)', function (): void {
    it('aprova o arquivo para o hash analisado, com registro append-only e auditoria', function (): void {
        $admin = User::factory()->admin()->create();
        $version = versionWithFiles();
        $file = fileOf($version, 'game.js');

        $this->actingAs($admin)->postJson(reviewUrl($version, $file), ['decision' => 'approved', 'sha256' => $file->sha256])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_file_review');

        $review = FileReview::sole();
        $entry = AuditEntry::sole();
        expect($file->fresh()?->review_status)->toBe(FileReviewStatus::Approved)
            ->and($review->sha256)->toBe($file->sha256)
            ->and($review->reviewer_id)->toBe($admin->id)
            ->and($review->decision)->toBe(FileDecision::Approved)
            // "Quem aprovou qual hash, quando."
            ->and($entry->action)->toBe('versioning.file-approved')
            ->and($entry->actor_id)->toBe($admin->id)
            ->and($entry->context)->toMatchArray(['path' => 'game.js', 'sha256' => $file->sha256, 'file_id' => $file->id])
            ->and($entry->occurred_at)->not->toBeNull();
    });

    it('hash diferente do analisado não aprova nada (I5)', function (): void {
        $version = versionWithFiles();
        $file = fileOf($version, 'game.js');

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(reviewUrl($version, $file), ['decision' => 'approved', 'sha256' => str_repeat('a', 64)])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O conteúdo analisado não é o deste arquivo (hash diferente). Abra o arquivo de novo antes de decidir.');

        expect($file->fresh()?->review_status)->toBe(FileReviewStatus::Pending)->and(FileReview::count())->toBe(0);
    });

    it('rejeitar um arquivo obrigatório rejeita a versão, com o motivo, e descarta a quarentena', function (): void {
        $admin = User::factory()->admin()->create();
        $version = versionWithFiles();
        $file = fileOf($version, 'game.js');

        $this->actingAs($admin)->postJson(reviewUrl($version, $file), ['decision' => 'rejected', 'sha256' => $file->sha256, 'reason' => 'Minerador de criptomoeda.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $version->refresh();
        expect($version->status)->toBe(VersionStatus::Rejected)
            ->and($version->rejected_by_id)->toBe($admin->id)
            ->and($version->rejection_reason)->toBe('Arquivo game.js rejeitado: Minerador de criptomoeda.')
            ->and($file->fresh()?->review_status)->toBe(FileReviewStatus::Rejected)
            ->and(AuditEntry::query()->orderBy('id')->pluck('action')->all())->toBe(['versioning.file-rejected', 'versioning.version-rejected'])
            ->and(Storage::disk('quarantine')->allFiles())->toBe([]);
    });

    it('a rejeição de arquivo exige motivo (I11)', function (): void {
        $version = versionWithFiles();
        $file = fileOf($version, 'game.js');

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(reviewUrl($version, $file), ['decision' => 'rejected', 'sha256' => $file->sha256])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        expect(fn () => app(ReviewFile::class)(User::factory()->admin()->create(), $version->id, $file->id, $file->sha256, FileDecision::Rejected, null))
            ->toThrow(InvalidDecisionReason::class);
    });

    it('valida a decisão e o formato do hash', function (array $payload, string $field): void {
        $version = versionWithFiles();
        $file = fileOf($version, 'game.js');

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(reviewUrl($version, $file), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        [['decision' => 'talvez', 'sha256' => str_repeat('a', 64)], 'decision'],
        [['decision' => 'approved', 'sha256' => str_repeat('A', 64)], 'sha256'],
        [['decision' => 'approved'], 'sha256'],
    ]);

    it('o admin pode mudar de ideia sobre uma aprovação; o histórico guarda as duas decisões', function (): void {
        $admin = User::factory()->admin()->create();
        $version = versionWithFiles();
        $file = fileOf($version, 'style.css');

        app(ReviewFile::class)($admin, $version->id, $file->id, $file->sha256, FileDecision::Approved, null);
        app(ReviewFile::class)($admin, $version->id, $file->id, $file->sha256, FileDecision::Rejected, DecisionReason::fromString('Revendo: CSS com tracking.'));

        expect($file->reviews()->pluck('decision')->all())->toBe([FileDecision::Approved, FileDecision::Rejected])
            ->and($file->fresh()?->review_status)->toBe(FileReviewStatus::Rejected);
    });

    it('arquivos só são avaliados em awaiting_file_review (I4)', function (): void {
        $version = versionWithFiles('awaitingCommitReview');
        $file = fileOf($version, 'game.js');

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(reviewUrl($version, $file), ['decision' => 'approved', 'sha256' => $file->sha256])
            ->assertUnprocessable();

        expect(FileReview::count())->toBe(0);
    });

    it('todos aprovados: a versão fica pronta para a publicação', function (): void {
        $admin = User::factory()->admin()->create();
        $version = versionWithFiles();
        foreach ($version->files as $file) {
            expect($version->fresh()?->allFilesApproved())->toBeFalse();
            app(ReviewFile::class)($admin, $version->id, $file->id, $file->sha256, FileDecision::Approved, null);
        }

        expect($version->fresh()?->allFilesApproved())->toBeTrue()
            ->and($version->fresh()?->status)->toBe(VersionStatus::AwaitingFileReview);
    });

    it('o admin rejeita a versão inteira com motivo', function (): void {
        $admin = User::factory()->admin()->create();
        $version = versionWithFiles();

        $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$version->id}/reject", ['reason' => 'Jogo duplicado.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        expect($version->fresh()?->rejection_reason)->toBe('Jogo duplicado.')
            ->and(AuditEntry::sole()->action)->toBe('versioning.version-rejected')
            ->and(Storage::disk('quarantine')->allFiles())->toBe([]);
    });

    it('o histórico de decisões é append-only no banco', function (): void {
        $version = versionWithFiles();
        $file = fileOf($version, 'game.js');
        app(ReviewFile::class)(User::factory()->admin()->create(), $version->id, $file->id, $file->sha256, FileDecision::Approved, null);

        expect(fn () => DB::table('file_reviews')->update(['decision' => 'rejected']))->toThrow(QueryException::class);
    });
});

describe('autorização (I1, I12)', function (): void {
    it('as Actions dos Portões 1 e 2 recusam quem não é admin, inclusive o dono do jogo', function (): void {
        $owner = User::factory()->developer()->create();
        $game = Game::factory()->for($owner, 'owner')->create();
        $commit = versionWithFiles('awaitingCommitReview', game: $game);
        $reason = DecisionReason::fromString('Não quero.');

        expect(fn () => app(ApproveCommit::class)($owner, $commit->id))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RejectCommit::class)($owner, $commit->id, $reason))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ShowFileContent::class)($owner, $commit->id, fileOf($commit, 'game.js')->id))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RejectVersion::class)($owner, $commit->id, $reason))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ReviewFile::class)($owner, $commit->id, fileOf($commit, 'game.js')->id, str_repeat('a', 64), FileDecision::Approved, null))->toThrow(AuthorizationException::class);
    });
});

describe('detalhe e histórico', function (): void {
    it('mostra as decisões de cada portão e o histórico de cada arquivo', function (): void {
        $admin = User::factory()->admin()->create(['name' => 'Rafael']);
        $version = versionWithFiles('awaitingCommitReview');
        app(ApproveCommit::class)($admin, $version->id);
        $file = fileOf($version, 'game.js');
        app(ReviewFile::class)($admin, $version->id, $file->id, $file->sha256, FileDecision::Approved, null);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/versions/{$version->id}")
            ->assertOk()
            ->assertJsonPath('data.commit_decision.decided_by.name', 'Rafael')
            ->assertJsonPath('data.commit_decision.rejection_reason', null)
            ->assertJsonPath('data.rejection', null);

        $files = collect($response->json('data.files'))->keyBy('path');
        expect($files['game.js']['review_status'])->toBe('approved')
            ->and($files['game.js']['reviews'])->toHaveCount(1)
            ->and($files['game.js']['reviews'][0])->toMatchArray(['decision' => 'approved', 'sha256' => $file->sha256, 'reason' => null])
            ->and($files['game.js']['reviews'][0]['reviewer']['name'])->toBe('Rafael')
            ->and($files['index.html']['reviews'])->toBe([]);
    });

    it('informa a aprovação anterior do mesmo conteúdo, sem herdá-la (ADR 0006)', function (): void {
        $admin = User::factory()->admin()->create(['name' => 'Rafael']);
        $game = Game::factory()->create();
        $first = versionWithFiles(game: $game);
        $old = fileOf($first, 'game.js');
        app(ReviewFile::class)($admin, $first->id, $old->id, $old->sha256, FileDecision::Approved, null);
        // Encerra a primeira (como se tivesse sido publicada) para abrir a segunda.
        DB::table('game_versions')->where('id', $first->id)->update(['status' => 'published', 'public_id' => fake()->uuid(), 'published_by_id' => $admin->id, 'published_at' => now()]);

        $second = versionWithFiles(game: $game, contents: ['style.css' => 'body { color: red }']);

        $files = collect($this->actingAs($admin)->getJson("/api/v1/admin/versions/{$second->id}")->json('data.files'))->keyBy('path');
        expect($files['game.js']['review_status'])->toBe('pending')
            ->and($files['game.js']['previous_approval'])->toMatchArray(['decision' => 'approved', 'version_number' => 1])
            ->and($files['game.js']['previous_approval']['reviewer']['name'])->toBe('Rafael')
            ->and($files['style.css']['previous_approval'])->toBeNull()
            ->and($files['index.html']['previous_approval'])->toBeNull();
    });

    it('o dono vê o motivo da rejeição em qualquer portão', function (): void {
        $owner = User::factory()->developer()->create();
        $version = versionWithFiles(game: Game::factory()->for($owner, 'owner')->create());
        app(RejectVersion::class)(User::factory()->admin()->create(), $version->id, DecisionReason::fromString('Jogo duplicado.'));

        $this->actingAs($owner)->getJson('/api/v1/me/versions')
            ->assertJsonPath('data.0.status', 'rejected')
            ->assertJsonPath('data.0.rejection_reason', 'Jogo duplicado.')
            ->assertJsonPath('data.0.in_progress', false);
    });
});

it('o banco exige motivo e decisor nas rejeições e o commit aprovado da revisão de arquivos em diante', function (array $attributes): void {
    expect(fn () => GameVersion::factory()->awaitingCommitReview()->create($attributes))->toThrow(QueryException::class);
})->with([
    'commit_rejected sem motivo' => [['status' => VersionStatus::CommitRejected]],
    'rejected sem motivo' => [['status' => VersionStatus::Rejected]],
    'awaiting_file_review sem decisor' => [['status' => VersionStatus::AwaitingFileReview]],
]);
