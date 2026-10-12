<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** J6: tira o jogo do ar na hora, com motivo (I11). O listener do Versioning remove o diretório servido. */
final readonly class BlockGame
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $gameId, DecisionReason $reason): Game
    {
        $this->gate->forUser($actor)->authorize(Permission::BlockGames->value);

        return $this->db->transaction(function () use ($actor, $gameId, $reason): Game {
            $game = Game::query()->lockForUpdate()->findOrFail($gameId);
            $game->block($actor, $reason);
            $this->publisher->publishFrom($game);

            return $game;
        });
    }
}
