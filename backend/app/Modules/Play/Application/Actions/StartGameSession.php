<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Identity\Domain\Exceptions\EmailNotVerified;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\PlayableGames;
use App\Modules\Play\Application\StartedSession;
use App\Modules\Play\Domain\Exceptions\GameNotPlayable;
use App\Modules\Play\Domain\GameSession;
use App\Modules\Play\Domain\PlayRules;
use App\Modules\Play\Domain\SessionStatus;
use App\Modules\Play\Domain\SessionToken;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionInterface;

/**
 * Começa uma partida na versão que está no ar (I9). A partida aberta anterior do mesmo jogo
 * expira: o jogador nunca tem duas sessões abertas no mesmo jogo.
 */
final readonly class StartGameSession
{
    public function __construct(
        private PlayableGames $playableGames,
        private PlayRules $rules,
        private ConnectionInterface $db,
        private Repository $config,
    ) {}

    public function __invoke(User $player, string $slug): StartedSession
    {
        if (! $player->hasVerifiedEmail()) {
            throw EmailNotVerified::forPlay();
        }

        [$game, $version] = $this->playableGames->bySlug($slug) ?? throw new GameNotPlayable;
        $token = SessionToken::issue();

        $session = $this->db->transaction(function () use ($player, $game, $version, $token): GameSession {
            // Serializa as partidas do jogador: duas abas iniciando juntas não violam "uma aberta por jogo".
            User::query()->lockForUpdate()->findOrFail($player->id);
            $now = CarbonImmutable::now();

            GameSession::query()
                ->where('user_id', $player->id)
                ->where('game_id', $game->id)
                ->where('status', SessionStatus::Open)
                ->get()
                ->each(fn (GameSession $open) => $open->expire($now));

            return GameSession::start($player, $game->id, $version->id, $token, $this->rules, $now);
        });

        $origin = (string) $this->config->get('publication.games_origin');

        return new StartedSession($session, $token->plain, "{$origin}/{$version->public_id}/index.html", $this->rules->minSessionSeconds);
    }
}
