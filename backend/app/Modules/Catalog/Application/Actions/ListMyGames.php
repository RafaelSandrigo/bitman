<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use Illuminate\Database\Eloquent\Collection;

/** "Meus jogos": o dono acompanha as próprias submissões, somente leitura. */
final readonly class ListMyGames
{
    /** @return Collection<int, Game> */
    public function __invoke(User $owner): Collection
    {
        return Game::query()->where('owner_id', $owner->id)->latest()->get();
    }
}
