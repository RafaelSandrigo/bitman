<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Permissões granulares. Cada caso de uso protegido checa uma permissão, não uma role.
 * As das ações de I1 (portões, publicação, bloqueio) entram nas fases que as implementam (ADR 0005).
 */
enum Permission: string
{
    case SubmitGames = 'games.submit';
    /** Fila de submissões e ajustes do catálogo (slug). */
    case ManageGames = 'games.manage';
    case ManageRoles = 'users.manage-roles';
    case ViewAudit = 'audit.view';
}
