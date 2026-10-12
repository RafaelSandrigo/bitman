<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

/** Arquivo obrigatório já gravado na quarentena, pronto para virar um VersionFile. */
final readonly class QuarantinedFile
{
    public function __construct(
        public string $path,
        public string $sha256,
        public int $size,
        public string $gitBlobSha,
        public ChangeType $changeType,
    ) {}
}
