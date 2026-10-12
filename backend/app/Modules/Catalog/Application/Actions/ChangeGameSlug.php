<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Exceptions\SlugAlreadyTaken;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\Slug;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class ChangeGameSlug
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $gameId, Slug $slug): Game
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageGames->value);

        $game = Game::findOrFail($gameId);

        if (Game::query()->where('slug', $slug->value)->whereKeyNot($game->id)->exists()) {
            throw SlugAlreadyTaken::for($slug);
        }

        try {
            $this->db->transaction(function () use ($game, $slug, $actor): void {
                $game->changeSlug($slug, $actor);
                $this->publisher->publishFrom($game);
            });
        } catch (UniqueConstraintViolationException) {
            // Outro jogo ficou com o slug entre a checagem e o commit.
            throw SlugAlreadyTaken::for($slug);
        }

        return $game;
    }
}
