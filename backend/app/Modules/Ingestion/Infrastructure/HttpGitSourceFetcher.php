<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Infrastructure;

use App\Modules\Ingestion\Domain\Exceptions\FetchFailed;
use App\Modules\Ingestion\Domain\Exceptions\InvalidSnapshot;
use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Ingestion\Domain\GitSourceFetcher;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\TreeEntry;
use App\Shared\Domain\Git\CommitSha;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\InvalidCommitSha;
use App\Shared\Domain\Git\RepositoryUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Adaptador da git-sandbox. Zero trust também aqui: a resposta é tratada como hostil (a sandbox
 * pode ter sido comprometida pelo repositório). Tamanho limitado, JSON estrito, paths em base64
 * estrito, só os blobs pedidos, e o snapshot confere cada blob contra o objeto da árvore.
 */
final readonly class HttpGitSourceFetcher implements GitSourceFetcher
{
    private const MAX_ENTRIES = 10_000;

    public function __construct(
        private Http $http,
        private LoggerInterface $logger,
        private string $baseUrl,
        private string $token,
        private int $timeoutSeconds,
        private int $maxResponseBytes,
    ) {}

    public function fetch(RepositoryUrl $url, GitRef $ref, array $paths, int $maxBlobBytes): RepositorySnapshot
    {
        $response = $this->request($url, $ref, $paths, $maxBlobBytes);

        if ($response->status() === 401) {
            $this->logger->error('git-sandbox recusou o token (SANDBOX_TOKEN diferente nos dois lados?)');

            throw new FetchFailed(FetchFailure::SandboxUnavailable);
        }

        $body = $this->decode($response);

        if (! $response->successful()) {
            $code = is_string($body['error'] ?? null) ? $body['error'] : '';

            throw new FetchFailed($response->status() >= 500 ? FetchFailure::SandboxUnavailable : FetchFailure::fromSandbox($code));
        }

        try {
            return $this->snapshot($body, $paths);
        } catch (InvalidSnapshot|InvalidCommitSha $e) {
            $this->logger->warning('Manifesto da git-sandbox recusado: '.$e->getMessage());

            throw new FetchFailed(FetchFailure::InvalidResponse, $e);
        }
    }

    /** @param  list<string>  $paths */
    private function request(RepositoryUrl $url, GitRef $ref, array $paths, int $maxBlobBytes): Response
    {
        $limit = $this->maxResponseBytes;

        try {
            return $this->http
                ->baseUrl($this->baseUrl)
                ->withToken($this->token)
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->connectTimeout(5)
                ->withOptions([
                    // Aborta a transferência assim que passar do teto (G7, sandbox comprometida).
                    'progress' => static function (int $total, int $downloaded) use ($limit): void {
                        if ($total > $limit || $downloaded > $limit) {
                            throw new RuntimeException('Resposta da git-sandbox acima do limite.');
                        }
                    },
                ])
                ->post('/fetch', [
                    'url' => $url->value,
                    'ref' => $ref->value,
                    'paths' => $paths,
                    'max_blob_bytes' => $maxBlobBytes,
                ]);
        } catch (ConnectionException $e) {
            $this->logger->error('git-sandbox inacessível: '.$e->getMessage());

            throw new FetchFailed(FetchFailure::SandboxUnavailable, $e);
        } catch (Throwable $e) {
            throw new FetchFailed(FetchFailure::InvalidResponse, $e);
        }
    }

    /** @return array<mixed> */
    private function decode(Response $response): array
    {
        $raw = $response->body();
        if (strlen($raw) > $this->maxResponseBytes) {
            throw new FetchFailed(FetchFailure::InvalidResponse);
        }

        try {
            $body = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new FetchFailed(FetchFailure::InvalidResponse, $e);
        }

        if (! is_array($body)) {
            throw new FetchFailed(FetchFailure::InvalidResponse);
        }

        return $body;
    }

    /**
     * @param  array<mixed>  $body
     * @param  list<string>  $paths
     */
    private function snapshot(array $body, array $paths): RepositorySnapshot
    {
        $commit = $body['commit'] ?? null;
        $entries = $body['entries'] ?? null;
        $blobs = $body['blobs'] ?? null;

        if (! is_string($commit) || ! is_array($entries) || ! array_is_list($entries) || count($entries) > self::MAX_ENTRIES || ! is_array($blobs)) {
            throw new InvalidSnapshot('estrutura do manifesto');
        }

        $tree = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw new InvalidSnapshot('entrada da árvore');
            }
            $size = $entry['size'] ?? null;
            if (! is_string($entry['mode'] ?? null) || ! is_string($entry['type'] ?? null) || ! is_string($entry['object'] ?? null) || ($size !== null && ! is_int($size))) {
                throw new InvalidSnapshot('campos da entrada da árvore');
            }
            $tree[] = new TreeEntry(self::base64($entry['path'] ?? null), $entry['mode'], $entry['type'], $entry['object'], $size);
        }

        $contents = [];
        foreach ($blobs as $path => $content) {
            if (! in_array($path, $paths, true)) {
                throw new InvalidSnapshot('blob que não foi pedido');
            }
            $contents[$path] = self::base64($content);
        }

        return new RepositorySnapshot(CommitSha::fromString($commit), $tree, $contents);
    }

    private static function base64(mixed $value): string
    {
        $decoded = is_string($value) ? base64_decode($value, true) : false;
        if ($decoded === false) {
            throw new InvalidSnapshot('base64 inválido');
        }

        return $decoded;
    }
}
