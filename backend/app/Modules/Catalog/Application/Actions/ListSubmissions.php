<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Jogos para o admin: a fila de submissões (ainda não publicados) ou os de outro estado, por ordem de chegada. */
final readonly class ListSubmissions
{
    private const PER_PAGE = 25;

    public function __construct(private Gate $gate) {}

    /** @return LengthAwarePaginator<int, Game> */
    public function __invoke(User $actor, GameStatus $status = GameStatus::Submitted): LengthAwarePaginator
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageGames->value);

        return Game::query()
            ->with('owner')
            ->where('status', $status)
            ->oldest()
            ->paginate(self::PER_PAGE);
    }
}
