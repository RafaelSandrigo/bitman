<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

/**
 * Resultado da política de repositório sobre um snapshot. Imutável e serializável: é guardado
 * na versão e mostrado ao admin no Portão 1.
 */
final readonly class ValidationReport
{
    /**
     * @param  list<Violation>  $violations
     * @param  list<IgnoredFile>  $ignored  no máximo `max_tree_entries` itens
     * @param  int  $ignoredTotal  quantos foram ignorados ao todo
     */
    public function __construct(
        public array $violations,
        public array $ignored,
        public int $ignoredTotal,
    ) {}

    /** I3: com qualquer violação, o Portão 1 não pode ser aprovado. */
    public function isBlocked(): bool
    {
        return $this->violations !== [];
    }

    public function hasViolation(string $code): bool
    {
        foreach ($this->violations as $violation) {
            if ($violation->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{blocked: bool, violations: list<array{code: string, message: string, path: string|null}>, ignored: list<array{path: string, mode: string, size: int|null}>, ignored_total: int}
     */
    public function toArray(): array
    {
        return [
            'blocked' => $this->isBlocked(),
            'violations' => array_map(static fn (Violation $v): array => $v->toArray(), $this->violations),
            'ignored' => array_map(static fn (IgnoredFile $f): array => $f->toArray(), $this->ignored),
            'ignored_total' => $this->ignoredTotal,
        ];
    }
}
