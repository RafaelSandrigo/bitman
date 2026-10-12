<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** O jogo volta ao ar com a mesma versão publicada, servida de novo depois do commit. */
final readonly class UnblockGame
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $gameId): Game
    {
        $this->gate->forUser($actor)->authorize(Permission::BlockGames->value);

        return $this->db->transaction(function () use ($actor, $gameId): Game {
            $game = Game::query()->lockForUpdate()->findOrFail($gameId);
            $game->unblock($actor);
            $this->publisher->publishFrom($game);

            return $game;
        });
    }
}
