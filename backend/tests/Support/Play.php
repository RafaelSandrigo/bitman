<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\GameSession;
use App\Modules\Play\Domain\Score;
use App\Modules\Versioning\Domain\GameVersion;
use Database\Factories\GameSessionFactory;

/** Cenários do Play: jogo jogável (I9), sessões e scores montados direto no banco. */
final class Play
{
    /** @return array{Game, GameVersion} */
    public static function playableGame(?Game $game = null, int $number = 1): array
    {
        $game ??= Game::factory()->published()->create();
        $version = GameVersion::factory()->for($game)->published()->create(['number' => $number]);
        Game::query()->whereKey($game->id)->update(['published_version_id' => $version->id]);

        return [$game->fresh() ?? $game, $version];
    }

    /** Sessão aberta há 10 minutos com o token GameSessionFactory::TOKEN. */
    public static function openSession(User $player, GameVersion $version): GameSession
    {
        return GameSession::factory()->create(['user_id' => $player->id, 'game_id' => $version->game_id, 'game_version_id' => $version->id]);
    }

    public static function score(User $player, GameVersion $version, int $value, array $attributes = []): Score
    {
        $session = GameSession::factory()->scored()->create(['user_id' => $player->id, 'game_id' => $version->game_id, 'game_version_id' => $version->id]);

        return Score::factory()->create(['game_session_id' => $session->id, 'value' => $value, ...$attributes]);
    }

    public const TOKEN = GameSessionFactory::TOKEN;
}
