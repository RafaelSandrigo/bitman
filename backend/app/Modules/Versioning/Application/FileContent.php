<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application;

use App\Modules\Versioning\Domain\VersionFile;

/** Conteúdo de um arquivo da quarentena, já conferido contra o SHA-256 registrado. */
final readonly class FileContent
{
    public function __construct(
        public VersionFile $file,
        public string $content,
    ) {}
}
