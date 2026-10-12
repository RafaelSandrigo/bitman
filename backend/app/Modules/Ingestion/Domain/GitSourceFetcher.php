<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

use App\Modules\Ingestion\Domain\Exceptions\FetchFailed;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;

/** Porta para obter um repositório. Em produção, a git-sandbox (ADR 0003). */
interface GitSourceFetcher
{
    /**
     * @param  list<string>  $paths  únicos paths cujo conteúdo é baixado
     *
     * @throws FetchFailed
     */
    public function fetch(RepositoryUrl $url, GitRef $ref, array $paths, int $maxBlobBytes): RepositorySnapshot;
}
