<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Score;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Histórico de partidas do jogador, da mais recente para a mais antiga (inclui as ocultas do ranking). */
final readonly class ListMyScores
{
    /** @return LengthAwarePaginator<int, Score> */
    public function __invoke(User $player): LengthAwarePaginator
    {
        return Score::query()
            ->where('user_id', $player->id)
            ->with(['game', 'version'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);
    }
}
