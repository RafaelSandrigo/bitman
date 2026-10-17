<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Actions;

use App\Modules\Audit\Application\AuditFilter;
use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consulta as entradas mais recentes, filtradas por entidade, ação, ator ou pela linha do tempo
 * de um jogo ou de um usuário.
 */
final readonly class ListAuditEntries
{
    private const PER_PAGE = 50;

    public function __construct(private Gate $gate) {}

    /** @return CursorPaginator<int, AuditEntry> */
    public function __invoke(User $actor, AuditFilter $filter): CursorPaginator
    {
        $this->gate->forUser($actor)->authorize(Permission::ViewAudit->value);

        return AuditEntry::query()
            ->with('actor')
            ->when($filter->subjectType !== null, fn (Builder $q) => $q->where('subject_type', $filter->subjectType))
            ->when($filter->subjectId !== null, fn (Builder $q) => $q->where('subject_id', $filter->subjectId))
            ->when($filter->action !== null, fn (Builder $q) => $q->where('action', $filter->action))
            ->when($filter->actorId !== null, fn (Builder $q) => $q->where('actor_id', $filter->actorId))
            ->when($filter->gameId !== null, fn (Builder $q) => $this->aboutGame($q, (string) $filter->gameId))
            ->when($filter->userId !== null, fn (Builder $q) => $this->aboutUser($q, (string) $filter->userId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }

    /** @param Builder<AuditEntry> $query */
    private function aboutGame(Builder $query, string $gameId): void
    {
        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('subject_type', 'game')->where('subject_id', $gameId))
            ->orWhere(fn (Builder $q) => $q->where('subject_type', 'game_version')
                ->whereIn('subject_id', DB::table('game_versions')->select('id')->where('game_id', $gameId)))
            ->orWhere(fn (Builder $q) => $q->where('subject_type', 'score')->whereRaw("context->>'game_id' = ?", [$gameId])));
    }

    /** @param Builder<AuditEntry> $query */
    private function aboutUser(Builder $query, string $userId): void
    {
        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->where('subject_type', 'user')->where('subject_id', $userId))
            ->orWhere('actor_id', $userId));
    }
}
