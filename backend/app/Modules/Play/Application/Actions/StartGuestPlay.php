<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Play\Application\PlayableGames;
use App\Modules\Play\Application\StartedGuestPlay;
use App\Modules\Play\Domain\Exceptions\GameNotPlayable;
use App\Modules\Play\Domain\GuestPlay;
use App\Modules\Play\Domain\PlayRules;
use App\Modules\Play\Domain\SessionToken;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;

/** Começa uma partida sem conta na versão que está no ar (I9). O limite é por IP (S2). */
final readonly class StartGuestPlay
{
    public function __construct(
        private PlayableGames $playableGames,
        private PlayRules $rules,
        private Repository $config,
    ) {}

    public function __invoke(string $slug): StartedGuestPlay
    {
        [$game, $version] = $this->playableGames->bySlug($slug) ?? throw new GameNotPlayable;
        $token = SessionToken::issue();
        $play = GuestPlay::start($game->id, $version->id, $token, $this->rules, CarbonImmutable::now());
        $origin = (string) $this->config->get('publication.games_origin');

        return new StartedGuestPlay($play, $token->plain, "{$origin}/{$version->public_id}/index.html", $this->rules->minSessionSeconds);
    }
}
