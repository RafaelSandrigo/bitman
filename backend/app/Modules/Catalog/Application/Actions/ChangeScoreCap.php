<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** M15: teto de score do jogo (`null` remove). Faz parte da moderação de scores (S2), auditado. */
final readonly class ChangeScoreCap
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $gameId, ?int $cap): Game
    {
        $this->gate->forUser($actor)->authorize(Permission::ModerateScores->value);

        return $this->db->transaction(function () use ($actor, $gameId, $cap): Game {
            $game = Game::query()->lockForUpdate()->findOrFail($gameId);
            $game->changeScoreCap($cap, $actor);
            $this->publisher->publishFrom($game);

            return $game;
        });
    }
}
