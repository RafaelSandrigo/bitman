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
    /** Portão 1: aprovar ou rejeitar o commit buscado. */
    case ApproveCommits = 'versions.approve-commit';
    /** Portão 2: ver o conteúdo, aprovar ou rejeitar cada arquivo e rejeitar a versão. */
    case ReviewFiles = 'files.review';
    case ManageRoles = 'users.manage-roles';
    case ViewAudit = 'audit.view';
}
