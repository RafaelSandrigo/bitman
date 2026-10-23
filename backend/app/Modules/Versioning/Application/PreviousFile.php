<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application;

/** M7: o mesmo arquivo na versão publicada, lido do archive e conferido contra o hash aprovado dela. */
final readonly class PreviousFile
{
    public function __construct(
        public int $versionNumber,
        public string $content,
    ) {}
}
