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
    /** Portão 0: autorizar ou rejeitar o acesso ao repositório, pedir nova importação e ver as versões. */
    case AuthorizeImports = 'versions.authorize-import';
    case ManageRoles = 'users.manage-roles';
    case ViewAudit = 'audit.view';
}
