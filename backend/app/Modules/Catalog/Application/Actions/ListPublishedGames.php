<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\PublishedGame;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Contracts\Config\Repository;

/**
 * Catálogo público (I9): só jogos `published` (não bloqueados) com versão `published`.
 * Também resolve um jogo pelo slug; qualquer outro estado responde como inexistente.
 */
final readonly class ListPublishedGames
{
    public function __construct(private Repository $config) {}

    /** @return list<PublishedGame> */
    public function __invoke(): array
    {
        $games = Game::query()->where('status', GameStatus::Published)->whereNotNull('published_version_id')->orderBy('name')->get();
        $versions = GameVersion::query()->whereKey($games->pluck('published_version_id'))->where('status', VersionStatus::Published)->get()->keyBy('id');

        $published = [];
        foreach ($games as $game) {
            $version = $versions->get((string) $game->published_version_id);
            if ($version !== null) {
                $published[] = $this->toPublished($game, $version);
            }
        }

        return $published;
    }

    public function bySlug(string $slug): ?PublishedGame
    {
        $game = Game::query()->where('slug', $slug)->where('status', GameStatus::Published)->whereNotNull('published_version_id')->first();
        $version = $game === null ? null : GameVersion::query()->whereKey($game->published_version_id)->where('status', VersionStatus::Published)->first();

        return $game === null || $version === null ? null : $this->toPublished($game, $version);
    }

    private function toPublished(Game $game, GameVersion $version): PublishedGame
    {
        $origin = (string) $this->config->get('publication.games_origin');

        return new PublishedGame($game, $version->number, $version->published_at ?? $version->updated_at, "{$origin}/{$version->public_id}/index.html");
    }
}
