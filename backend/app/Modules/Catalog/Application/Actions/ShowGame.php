<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\AdminGameDetails;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\GameVersion;
use Illuminate\Contracts\Auth\Access\Gate;

/** Detalhe do jogo para o admin: estado, bloqueio e se a versão publicada está de fato no ar. */
final readonly class ShowGame
{
    public function __construct(
        private Gate $gate,
        private ArtifactStore $artifacts,
    ) {}

    public function __invoke(User $actor, string $gameId): AdminGameDetails
    {
        $this->gate->forUser($actor)->authorize(Permission::ManageGames->value);

        $game = Game::query()->with('owner')->findOrFail($gameId);
        $publicId = $game->published_version_id === null ? null : GameVersion::query()->whereKey($game->published_version_id)->value('public_id');

        return new AdminGameDetails($game, is_string($publicId) && $this->artifacts->isServed($publicId));
    }
}
