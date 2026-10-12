<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Ingestion\Domain\Exceptions\FetchFailed;
use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Ingestion\Domain\GitSourceFetcher;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;
use Throwable;

/** Fetcher em memória: devolve o snapshot configurado (ou falha) e registra as chamadas. */
final class FakeGitSourceFetcher implements GitSourceFetcher
{
    /** @var list<array{url: string, ref: string, paths: list<string>, max: int}> */
    public array $calls = [];

    private function __construct(
        private readonly ?RepositorySnapshot $snapshot,
        private readonly ?Throwable $error,
    ) {}

    public static function returning(RepositorySnapshot $snapshot): self
    {
        return new self($snapshot, null);
    }

    public static function failing(FetchFailure|Throwable $failure): self
    {
        return new self(null, $failure instanceof FetchFailure ? new FetchFailed($failure) : $failure);
    }

    public function fetch(RepositoryUrl $url, GitRef $ref, array $paths, int $maxBlobBytes): RepositorySnapshot
    {
        $this->calls[] = ['url' => $url->value, 'ref' => $ref->value, 'paths' => $paths, 'max' => $maxBlobBytes];

        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->snapshot ?? throw new FetchFailed(FetchFailure::FetchError);
    }
}
