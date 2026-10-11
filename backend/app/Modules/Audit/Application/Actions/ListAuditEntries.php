<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Actions;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Consulta por entidade (subjectType + subjectId) ou, sem filtro, as entradas mais recentes.
 */
final readonly class ListAuditEntries
{
    private const PER_PAGE = 50;

    public function __construct(private Gate $gate) {}

    /** @return CursorPaginator<int, AuditEntry> */
    public function __invoke(User $actor, ?string $subjectType, ?string $subjectId): CursorPaginator
    {
        $this->gate->forUser($actor)->authorize(Permission::ViewAudit->value);

        return AuditEntry::query()
            ->with('actor')
            ->when($subjectType !== null, fn ($q) => $q->where('subject_type', $subjectType))
            ->when($subjectId !== null, fn ($q) => $q->where('subject_id', $subjectId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
