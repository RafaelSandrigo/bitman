<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Ingestion\Domain\GitSourceFetcher;
use App\Modules\Versioning\Application\Actions\ImportRepository;
use App\Modules\Versioning\Domain\ChangeType;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantinedFile;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;
use App\Modules\Versioning\Infrastructure\Jobs\FetchRepositoryJob;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeGitSourceFetcher;
use Tests\Support\Snapshots;

/*
| Busca autorizada → snapshot → política → quarentena → Portão 1 (I13, G6, G7).
*/

beforeEach(function (): void {
    Storage::fake('quarantine');
    Http::preventStrayRequests();
});

function useFetcher(FakeGitSourceFetcher $fetcher): FakeGitSourceFetcher
{
    app()->instance(GitSourceFetcher::class, $fetcher);

    return $fetcher;
}

function import(GameVersion $version): GameVersion
{
    app(ImportRepository::class)($version->id);

    return $version->fresh() ?? throw new RuntimeException('versão sumiu');
}

it('fluxo completo: autorizar pela API leva a versão ao Portão 1 na mesma requisição (fila síncrona)', function (): void {
    $fetcher = useFetcher(FakeGitSourceFetcher::returning(Snapshots::valid()));
    $admin = User::factory()->admin()->create();
    $version = GameVersion::factory()->create(['requested_ref' => 'v1.0']);

    $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$version->id}/authorize-import")->assertOk();

    expect($version->fresh()?->status)->toBe(VersionStatus::AwaitingCommitReview)
        ->and($fetcher->calls)->toBe([[
            'url' => $version->repository_url,
            'ref' => 'v1.0',
            'paths' => ['index.html', 'style.css', 'game.js'],
            'max' => 1024 * 1024,
        ]])
        ->and(AuditEntry::query()->orderBy('id')->pluck('action')->all())->toBe(['versioning.import-authorized', 'versioning.repository-fetched']);
});

it('registra o commit, o relatório e os arquivos na quarentena (I13)', function (): void {
    useFetcher(FakeGitSourceFetcher::returning(Snapshots::of(Snapshots::gameFiles(), [Snapshots::entry('README.md', size: 7)])));
    $version = import(GameVersion::factory()->fetching()->create());

    expect($version->status)->toBe(VersionStatus::AwaitingCommitReview)
        ->and($version->commit_sha)->toBe(Snapshots::COMMIT)
        ->and($version->fetched_at)->not->toBeNull()
        // jsonb não preserva a ordem das chaves.
        ->and($version->validation_report)->toEqual([
            'blocked' => false,
            'violations' => [],
            'ignored' => [['path' => 'README.md', 'mode' => '100644', 'size' => 7]],
            'ignored_total' => 1,
        ]);

    $files = $version->files;
    expect($files->pluck('path')->all())->toBe(['game.js', 'index.html', 'style.css']);
    foreach ($files as $file) {
        $content = Snapshots::gameFiles()[$file->path];
        expect($file->sha256)->toBe(hash('sha256', $content))
            ->and($file->size)->toBe(strlen($content))
            ->and($file->git_blob_sha)->toBe(sha1('blob '.strlen($content)."\0".$content))
            ->and($file->change_type)->toBe(ChangeType::Added)
            ->and($file->review_status->value)->toBe('pending');
        // G6: caminho = nossos ids, nunca o nome vindo do Git.
        Storage::disk('quarantine')->assertExists(strtolower($version->id).'/'.$file->sha256);
        expect(Storage::disk('quarantine')->get(strtolower($version->id).'/'.$file->sha256))->toBe($content);
    }
    expect(Storage::disk('quarantine')->allFiles())->toHaveCount(3);

    $entry = AuditEntry::sole();
    expect($entry->action)->toBe('versioning.repository-fetched')
        ->and($entry->actor_id)->toBeNull()
        ->and($entry->context)->toMatchArray(['commit' => Snapshots::COMMIT, 'blocked' => false, 'violations' => 0, 'files' => 3]);
});

it('com violação, a versão termina em validation_failed, com commit e relatório e nada na quarentena', function (): void {
    useFetcher(FakeGitSourceFetcher::returning(Snapshots::replacing('game.js', Snapshots::entry('game.js', '120000'))));
    $version = import(GameVersion::factory()->fetching()->create());

    expect($version->status)->toBe(VersionStatus::ValidationFailed)
        ->and($version->status->isInProgress())->toBeFalse()
        ->and($version->commit_sha)->toBe(Snapshots::COMMIT)
        ->and($version->validation_report['blocked'] ?? null)->toBeTrue()
        ->and(array_column($version->validation_report['violations'] ?? [], 'code'))->toBe(['required_file_not_regular', 'symlink'])
        ->and($version->files)->toBeEmpty()
        ->and(Storage::disk('quarantine')->allFiles())->toBe([])
        ->and(AuditEntry::sole()->context)->toMatchArray(['blocked' => true, 'violations' => 2, 'files' => 0]);
});

it('o banco recusa validation_failed sem relatório bloqueado', function (): void {
    expect(fn () => GameVersion::factory()->awaitingCommitReview()->create(['status' => VersionStatus::ValidationFailed]))
        ->toThrow(QueryException::class);
});

it('marca o diff em relação à versão publicada do jogo', function (): void {
    $game = Game::factory()->published()->create();
    $published = GameVersion::factory()->for($game)->published()->create();
    $files = Snapshots::gameFiles();
    foreach (['index.html' => $files['index.html'], 'style.css' => 'body {}', 'game.js' => $files['game.js']] as $path => $content) {
        VersionFile::record($published, new QuarantinedFile($path, hash('sha256', $content), strlen($content), sha1($content), ChangeType::Added));
    }

    useFetcher(FakeGitSourceFetcher::returning(Snapshots::valid()));
    $version = import(GameVersion::factory()->for($game)->fetching()->create(['number' => 2]));

    expect($version->files->mapWithKeys(fn (VersionFile $f) => [$f->path => $f->change_type->value])->all())
        ->toBe(['game.js' => 'unchanged', 'index.html' => 'unchanged', 'style.css' => 'modified']);
});

it('falha da busca: versão em fetch_failed, auditada, sem nada na quarentena', function (FetchFailure $failure): void {
    useFetcher(FakeGitSourceFetcher::failing($failure));
    $version = import(GameVersion::factory()->fetching()->create());

    $entry = AuditEntry::sole();
    expect($version->status)->toBe(VersionStatus::FetchFailed)
        ->and($version->fetchFailure())->toBe($failure)
        ->and($version->commit_sha)->toBeNull()
        ->and($entry->action)->toBe('versioning.fetch-failed')
        ->and($entry->context)->toMatchArray(['failure' => $failure->value])
        ->and(Storage::disk('quarantine')->allFiles())->toBe([]);
})->with([FetchFailure::RefNotFound, FetchFailure::Timeout, FetchFailure::InvalidObjects, FetchFailure::SandboxUnavailable]);

it('erro inesperado vira fetch_failed interno, nunca erro não tratado, e limpa a quarentena', function (): void {
    useFetcher(FakeGitSourceFetcher::returning(Snapshots::valid()));
    $version = GameVersion::factory()->fetching()->create();
    // A transação final falha depois de a quarentena já ter sido escrita.
    GameVersion::saving(function (GameVersion $v): void {
        if ($v->status === VersionStatus::AwaitingCommitReview) {
            throw new RuntimeException('banco fora do ar');
        }
    });

    $version = import($version);

    expect($version->status)->toBe(VersionStatus::FetchFailed)
        ->and($version->fetchFailure())->toBe(FetchFailure::Internal)
        ->and(VersionFile::count())->toBe(0)
        ->and(Storage::disk('quarantine')->allFiles())->toBe([]);
});

it('URL ou ref inválidas no banco não chegam à sandbox (G1–G3, revalidação)', function (): void {
    $fetcher = useFetcher(FakeGitSourceFetcher::returning(Snapshots::valid()));
    $version = GameVersion::factory()->fetching()->create(['requested_ref' => '--upload-pack=x']);

    expect(import($version)->fetchFailure())->toBe(FetchFailure::RequestRefused)
        ->and($fetcher->calls)->toBe([]);
});

it('só processa versões em busca (idempotente)', function (string $state): void {
    $fetcher = useFetcher(FakeGitSourceFetcher::returning(Snapshots::valid()));
    $version = GameVersion::factory()->{$state}()->create();

    expect(import($version)->status)->toBe($version->status)
        ->and($fetcher->calls)->toBe([])
        ->and(AuditEntry::count())->toBe(0);
})->with(['fetchFailed', 'validationFailed', 'awaitingCommitReview']);

it('uma falha tardia não desfaz nem apaga uma busca já concluída', function (): void {
    useFetcher(FakeGitSourceFetcher::returning(Snapshots::valid()));
    $version = import(GameVersion::factory()->fetching()->create());

    (new FetchRepositoryJob($version->id))->failed(new RuntimeException('job duplicado'));

    expect($version->fresh()?->status)->toBe(VersionStatus::AwaitingCommitReview)
        ->and(Storage::disk('quarantine')->allFiles())->toHaveCount(3);
});

it('o job que morre (tempo ou memória) leva a versão a fetch_failed', function (): void {
    $version = GameVersion::factory()->fetching()->create();

    (new FetchRepositoryJob($version->id))->failed(new RuntimeException('timeout do worker'));

    expect($version->fresh()?->fetchFailure())->toBe(FetchFailure::Internal);
});
