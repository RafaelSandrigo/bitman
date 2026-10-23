<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Score;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Moderação (S2): sinalizados em revisão (M20), ocultos ou todos; opcionalmente de um jogo. */
final readonly class ListScores
{
    public const FLAGGED = 'flagged';

    public const HIDDEN = 'hidden';

    public const ALL = 'all';

    public function __construct(private Gate $gate) {}

    /** @return LengthAwarePaginator<int, Score> */
    public function __invoke(User $actor, string $filter, ?string $gameId): LengthAwarePaginator
    {
        $this->gate->forUser($actor)->authorize(Permission::ModerateScores->value);

        return Score::query()
            ->when($filter === self::FLAGGED, fn ($q) => $q->where('flagged', true)->whereNull('hidden_at')->whereNull('flag_cleared_at'))
            ->when($filter === self::HIDDEN, fn ($q) => $q->whereNotNull('hidden_at'))
            ->when($gameId !== null, fn ($q) => $q->where('game_id', $gameId))
            ->with(['game', 'version', 'player', 'session', 'hiddenBy', 'flagClearedBy'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);
    }
}
