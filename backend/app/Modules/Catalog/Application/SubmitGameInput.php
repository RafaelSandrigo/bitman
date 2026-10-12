<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;

/** Dados da submissão já validados; a URL e a ref chegam como value objects (G1–G3). */
final readonly class SubmitGameInput
{
    public function __construct(
        public string $name,
        public string $description,
        public string $authorName,
        public RepositoryUrl $repositoryUrl,
        public GitRef $ref,
        /** Arquivo temporário do upload; o ThumbnailProcessor decide se ele vira thumbnail. */
        public string $thumbnailPath,
    ) {}
}
