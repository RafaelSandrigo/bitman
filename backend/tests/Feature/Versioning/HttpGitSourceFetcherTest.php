<?php

declare(strict_types=1);

use App\Modules\Ingestion\Domain\Exceptions\FetchFailed;
use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Ingestion\Domain\GitSourceFetcher;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/*
| Adaptador da git-sandbox: a resposta é hostil até prova em contrário (G8, sandbox comprometida).
*/

const SANDBOX = 'http://git-sandbox.test';

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['ingestion.sandbox.url' => SANDBOX, 'ingestion.sandbox.token' => str_repeat('t', 40)]);
});

/** Manifesto coerente, com overrides. */
function manifest(array $override = []): array
{
    $content = "console.log(1);\n";

    return [
        'commit' => str_repeat('a', 40),
        'entries' => [['path' => base64_encode('game.js'), 'mode' => '100644', 'type' => 'blob', 'object' => RepositorySnapshot::gitBlobId($content), 'size' => strlen($content)]],
        'blobs' => ['game.js' => base64_encode($content)],
        ...$override,
    ];
}

function fetchWith(mixed $body, int $status = 200): FetchFailure|RepositorySnapshot
{
    Http::fake([SANDBOX.'/fetch' => is_string($body) ? Http::response($body, $status) : Http::response($body, $status)]);

    try {
        return app(GitSourceFetcher::class)->fetch(RepositoryUrl::fromString('https://github.com/ada/snake'), GitRef::fromString('main'), ['index.html', 'style.css', 'game.js'], 1024);
    } catch (FetchFailed $e) {
        return $e->failure;
    }
}

it('monta o snapshot a partir de um manifesto coerente', function (): void {
    $snapshot = fetchWith(manifest());

    expect($snapshot)->toBeInstanceOf(RepositorySnapshot::class)
        ->and($snapshot->commit->value)->toBe(str_repeat('a', 40))
        ->and($snapshot->blob('game.js'))->toBe("console.log(1);\n");
});

it('mapeia os códigos de erro da sandbox', function (string $code, FetchFailure $failure): void {
    expect(fetchWith(['error' => $code], 422))->toBe($failure);
})->with([
    ['ref_not_found', FetchFailure::RefNotFound],
    ['repository_unavailable', FetchFailure::RepositoryUnavailable],
    ['redirected', FetchFailure::Redirected],
    ['timeout', FetchFailure::Timeout],
    ['invalid_objects', FetchFailure::InvalidObjects],
    ['invalid_request', FetchFailure::RequestRefused],
    'código desconhecido' => ['<script>', FetchFailure::FetchError],
    'código reservado ao Bitman' => ['sandbox_unavailable', FetchFailure::FetchError],
]);

it('token recusado, erro 5xx ou sandbox fora do ar → indisponível', function (): void {
    expect(fetchWith(['error' => 'unauthorized'], 401))->toBe(FetchFailure::SandboxUnavailable)
        ->and(fetchWith(['error' => 'internal'], 500))->toBe(FetchFailure::SandboxUnavailable);

    Http::fake(fn () => throw new ConnectionException('recusada'));
    expect(fn () => app(GitSourceFetcher::class)->fetch(RepositoryUrl::fromString('https://github.com/ada/snake'), GitRef::fromString('main'), ['game.js'], 1024))
        ->toThrow(fn (FetchFailed $e) => expect($e->failure)->toBe(FetchFailure::SandboxUnavailable));
});

it('recusa respostas incoerentes ou hostis', function (mixed $body): void {
    expect(fetchWith($body))->toBe(FetchFailure::InvalidResponse);
})->with([
    'não é JSON' => 'oi',
    'JSON escalar' => '"x"',
    'sem commit' => fn () => manifest(['commit' => null]),
    'commit inválido' => fn () => manifest(['commit' => 'HEAD']),
    'entries não é lista' => fn () => manifest(['entries' => ['a' => 1]]),
    'path sem base64' => fn () => manifest(['entries' => [[...manifest()['entries'][0], 'path' => 'game.js!']]]),
    'modo inválido' => fn () => manifest(['entries' => [[...manifest()['entries'][0], 'mode' => 'rwx']]]),
    'tamanho como texto' => fn () => manifest(['entries' => [[...manifest()['entries'][0], 'size' => '16']]]),
    'blob não pedido' => fn () => manifest(['blobs' => ['../../etc/passwd' => base64_encode('x')]]),
    'blob trocado' => fn () => manifest(['blobs' => ['game.js' => base64_encode("alert('trocado');\n")]]),
    'blob sem entrada' => fn () => manifest(['entries' => []]),
    'blob em base64 inválido' => fn () => manifest(['blobs' => ['game.js' => '***']]),
    'JSON fundo demais' => fn () => manifest(['x' => [[[[[[[[[[1]]]]]]]]]]]),
]);

it('descarta resposta acima do teto (G7)', function (): void {
    config(['ingestion.sandbox.max_response_bytes' => 500]);

    expect(fetchWith(manifest(['padding' => str_repeat('x', 1000)])))->toBe(FetchFailure::InvalidResponse);
});
