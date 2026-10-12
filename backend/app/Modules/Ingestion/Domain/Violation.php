<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

/** Uma regra da política não foi cumprida. Toda violação bloqueia a versão (I3). */
final readonly class Violation
{
    public function __construct(
        /** Identificador estável da regra (ex.: `symlink`), para a UI e os testes. */
        public string $code,
        /** Mensagem para o admin (pt-BR), com o path já escapado. */
        public string $message,
        public ?string $path = null,
    ) {}

    public static function at(string $code, string $message, TreeEntry $entry): self
    {
        return new self($code, $message, $entry->displayPath());
    }

    /** @return array{code: string, message: string, path: string|null} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'path' => $this->path];
    }
}
