<?php

declare(strict_types=1);

namespace App\Modules\Play\Application;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Play\Domain\Exceptions\GameNotPlayable;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;

/** I9 visto pelo Play: jogo `published` (não bloqueado) com a versão `published` no ar. */
final readonly class PlayableGames
{
    /** @return array{Game, GameVersion}|null */
    public function bySlug(string $slug): ?array
    {
        $game = Game::query()->where('slug', $slug)->where('status', GameStatus::Published)->whereNotNull('published_version_id')->first();
        $version = $game === null ? null : GameVersion::query()
            ->whereKey($game->published_version_id)
            ->where('status', VersionStatus::Published)
            ->whereNotNull('public_id')
            ->first();

        return $game === null || $version === null ? null : [$game, $version];
    }

    /**
     * Jogo bloqueado ou versão retirada durante a partida: o score não entra. Versão substituída
     * por uma nova durante a partida continua valendo (o ranking considera todas as versões).
     */
    public function assertStillAcceptsScores(string $gameId, string $versionId): void
    {
        $game = Game::query()->find($gameId);
        $version = GameVersion::query()->find($versionId);

        if ($game?->status !== GameStatus::Published
            || ! in_array($version?->status, [VersionStatus::Published, VersionStatus::Superseded], true)) {
            throw new GameNotPlayable;
        }
    }
}
