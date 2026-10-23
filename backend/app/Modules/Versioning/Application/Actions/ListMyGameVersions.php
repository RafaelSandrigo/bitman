<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** M2: o histórico completo de versões de um jogo, só para o dono. Jogo de outra pessoa = 404. */
final readonly class ListMyGameVersions
{
    /** @return array{Game, LengthAwarePaginator<int, GameVersion>} */
    public function __invoke(User $owner, string $gameId): array
    {
        $game = Game::query()->where('owner_id', $owner->id)->findOrFail($gameId);
        $versions = GameVersion::query()->where('game_id', $game->id)->orderByDesc('number')->paginate(20);

        return [$game, $versions];
    }
}
