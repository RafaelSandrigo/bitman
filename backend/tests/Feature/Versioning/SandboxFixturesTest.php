<?php

declare(strict_types=1);

use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Versioning\Application\Actions\ImportRepository;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
| Aceite da Fase 3: os manifestos que a git-sandbox REAL gerou para os repositórios-fixture
| (tests/sandbox, conferidos por tests/infra/sandbox-fixtures.sh) passam pelo adaptador HTTP e
| pelo pipeline do Laravel. Toda fixture maliciosa termina em violação bloqueante ou em
| fetch_failed, nunca em erro não tratado; a válida chega limpa ao Portão 1.
*/

const SANDBOX_TOKEN_FOR_TESTS = 'token-de-teste-com-mais-de-32-caracteres';

/** @return array<string, array{status: int, body: array<mixed>}> */
function sandboxManifests(): array
{
    /** @var array<string, array{status: int, body: array<mixed>}> */
    return json_decode((string) file_get_contents(base_path('tests/Fixtures/sandbox/manifests.json')), true, 512, JSON_THROW_ON_ERROR);
}

function importFixture(string $fixture): GameVersion
{
    $manifest = sandboxManifests()[$fixture];
    Http::fake(['git-sandbox.test/fetch' => Http::response($manifest['body'], $manifest['status'])]);

    $version = GameVersion::factory()->fetching()->create();
    app(ImportRepository::class)($version->id);

    return $version->fresh() ?? throw new RuntimeException('versão sumiu');
}

beforeEach(function (): void {
    Storage::fake('quarantine');
    Http::preventStrayRequests();
    config(['ingestion.sandbox.url' => 'http://git-sandbox.test', 'ingestion.sandbox.token' => SANDBOX_TOKEN_FOR_TESTS]);
});

it('o golden cobre todas as fixtures do harness', function (): void {
    expect(array_keys(sandboxManifests()))->toBe([
        'valid', 'valid-tag', 'symlink', 'symlink-extra', 'submodule', 'executable', 'huge', 'binary',
        'hostile-names', 'dotdot-tree', 'dotgit-tree', 'too-many', 'way-too-many', 'missing-files', 'empty',
    ]);
});

it('o jogo válido chega ao Portão 1 sem violações, com o commit e os 3 arquivos', function (string $fixture): void {
    $version = importFixture($fixture);
    $commit = sandboxManifests()[$fixture]['body']['commit'];

    expect($version->status)->toBe(VersionStatus::AwaitingCommitReview)
        ->and($version->commit_sha)->toBe($commit)
        ->and($version->validation_report['violations'] ?? null)->toBe([])
        ->and(array_column($version->validation_report['ignored'] ?? [], 'path'))->toBe(['README.md', 'assets/logo.png'])
        ->and($version->files->pluck('path')->all())->toBe(['game.js', 'index.html', 'style.css']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://git-sandbox.test/fetch'
        && $request->hasHeader('Authorization', 'Bearer '.SANDBOX_TOKEN_FOR_TESTS)
        && $request->data() === [
            'url' => $version->repository_url,
            'ref' => 'main',
            'paths' => ['index.html', 'style.css', 'game.js'],
            'max_blob_bytes' => 1024 * 1024,
        ]);
})->with(['valid', 'valid-tag']);

it('fixture maliciosa → violação bloqueante (I3)', function (string $fixture, array $codes): void {
    $version = importFixture($fixture);

    expect($version->status)->toBe(VersionStatus::AwaitingCommitReview)
        ->and($version->validation_report['blocked'] ?? null)->toBeTrue()
        ->and(array_column($version->validation_report['violations'] ?? [], 'code'))->toBe($codes);
})->with([
    'symlink no obrigatório' => ['symlink', ['required_file_not_regular', 'symlink']],
    'symlink fora dos obrigatórios' => ['symlink-extra', ['symlink']],
    'submodule' => ['submodule', ['submodule', 'submodule']],
    'executável' => ['executable', ['required_file_not_regular']],
    'arquivo gigante' => ['huge', ['file_too_large']],
    'binário' => ['binary', ['not_text']],
    'nomes hostis' => ['hostile-names', ['unsafe_path', 'unsafe_path', 'unsafe_path', 'unsafe_path']],
    'arquivos demais' => ['too-many', ['too_many_entries']],
    'sem os obrigatórios' => ['missing-files', ['required_file_missing', 'required_file_missing', 'required_file_missing']],
    'commit vazio' => ['empty', ['required_file_missing', 'required_file_missing', 'required_file_missing']],
]);

it('fixture maliciosa → fetch_failed', function (string $fixture, FetchFailure $failure): void {
    $version = importFixture($fixture);

    expect($version->status)->toBe(VersionStatus::FetchFailed)
        ->and($version->fetchFailure())->toBe($failure)
        ->and(Storage::disk('quarantine')->allFiles())->toBe([]);
})->with([
    'árvore com ..' => ['dotdot-tree', FetchFailure::InvalidObjects],
    'árvore com .git' => ['dotgit-tree', FetchFailure::InvalidObjects],
    'árvore acima do teto' => ['way-too-many', FetchFailure::TooManyEntries],
]);

it('nomes hostis aparecem escapados para o admin', function (): void {
    $version = importFixture('hostile-names');

    expect(array_column($version->validation_report['violations'] ?? [], 'path'))
        ->toBe(['a\\\\b.js', 'controle\x01.txt', 'invalido\xFF.txt', 'quebra\x0Ade-linha.txt']);
});

it('o blob acima do limite nunca é baixado para a aplicação (G7)', function (): void {
    $manifest = sandboxManifests()['huge']['body'];

    expect($manifest['blobs'])->not->toHaveKey('game.js')
        ->and(importFixture('huge')->files->pluck('path')->all())->toBe(['index.html', 'style.css']);
});
