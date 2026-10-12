<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Fila de submissões do admin: jogos ainda não publicados, por ordem de chegada. */
final readonly class ListSubmissions
{
    private const PER_PAGE = 25;

    public function __construct(private Gate $gate) {}

    /** @return LengthAwarePaginator<int, Game> */
    public function __invoke(User $actor): LengthAwarePaginator
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageGames->value);

        return Game::query()
            ->with('owner')
            ->where('status', GameStatus::Submitted)
            ->oldest()
            ->paginate(self::PER_PAGE);
    }
}
