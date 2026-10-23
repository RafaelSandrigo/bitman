<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application;

use App\Modules\Versioning\Domain\VersionFile;

/**
 * Conteúdo de um arquivo da quarentena, já conferido contra o SHA-256 registrado. Para um arquivo
 * `modified`, o da versão publicada (M7); `previousUnavailable` quando ele não pôde ser conferido.
 */
final readonly class FileContent
{
    public function __construct(
        public VersionFile $file,
        public string $content,
        public ?PreviousFile $previous = null,
        public bool $previousUnavailable = false,
    ) {}
}
