<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use InvalidArgumentException;

/**
 * O que um evento auditável declara sobre si: quem fez o quê, sobre qual entidade e por quê.
 */
final readonly class AuditRecord
{
    /**
     * @param  array<string, scalar|null>  $context  Estado analisado (ex.: role, commit, hash)
     */
    public function __construct(
        public string $action,
        public string $subjectType,
        public string $subjectId,
        public ?string $actorId,
        public array $context = [],
        public ?string $reason = null,
    ) {
        if (preg_match('/^[a-z]+(\.[a-z-]+)+$/', $action) !== 1) {
            throw new InvalidArgumentException("Ação de auditoria inválida: {$action}");
        }

        if ($subjectType === '' || $subjectId === '') {
            throw new InvalidArgumentException('A entidade auditada é obrigatória.');
        }
    }
}
