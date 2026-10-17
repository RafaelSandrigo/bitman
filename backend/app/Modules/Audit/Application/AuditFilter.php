<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

/** Filtros da consulta de auditoria. Todos opcionais e combináveis. */
final readonly class AuditFilter
{
    public function __construct(
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $action = null,
        public ?string $actorId = null,
        /** Linha do tempo do jogo: o jogo, as versões dele e os scores dele. */
        public ?string $gameId = null,
        /** Linha do tempo do usuário: o que aconteceu com ele e o que ele fez. */
        public ?string $userId = null,
    ) {}
}
