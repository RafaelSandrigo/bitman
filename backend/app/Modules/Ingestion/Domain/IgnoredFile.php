<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

/** Entrada fora da allowlist: só nome, modo e tamanho; o conteúdo nunca entra na versão. */
final readonly class IgnoredFile
{
    public function __construct(
        public string $path,
        public string $mode,
        public ?int $size,
    ) {}

    /** @return array{path: string, mode: string, size: int|null} */
    public function toArray(): array
    {
        return ['path' => $this->path, 'mode' => $this->mode, 'size' => $this->size];
    }
}
