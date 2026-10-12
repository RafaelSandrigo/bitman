<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/** "Meus jogos": a versão mais recente de cada jogo do dono, para ele ver onde ela parou e por quê. */
final readonly class ListMyLatestVersions
{
    /** @return Collection<int, GameVersion> */
    public function __invoke(User $owner): Collection
    {
        return GameVersion::query()
            ->whereHas('game', fn (Builder $game) => $game->where('owner_id', $owner->id))
            ->whereNotExists(fn (QueryBuilder $newer) => $newer->from('game_versions', 'newer')
                ->whereColumn('newer.game_id', 'game_versions.game_id')
                ->whereColumn('newer.number', '>', 'game_versions.number'))
            ->get();
    }
}
