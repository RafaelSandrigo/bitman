<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\Exceptions\EmailNotVerified;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\ClaimedScore;
use App\Modules\Play\Application\PersonalBests;
use App\Modules\Play\Application\PlayableGames;
use App\Modules\Play\Application\RecordedScore;
use App\Modules\Play\Domain\AnomalyPolicy;
use App\Modules\Play\Domain\Exceptions\GuestScoreAlreadyClaimed;
use App\Modules\Play\Domain\Exceptions\InvalidSessionToken;
use App\Modules\Play\Domain\GuestPlay;
use App\Modules\Play\Domain\Leaderboard;
use App\Modules\Play\Domain\Score;
use App\Modules\Play\Domain\ScoreDistribution;
use App\Modules\Play\Domain\ScoreValue;
use App\Shared\Application\DomainEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Salva na conta o score pendente de uma partida de visitante (A5: só e-mail confirmado). A
 * partida é travada na transação; o `unique(claimed_score_id)` é a última barreira contra dois saves.
 */
final readonly class ClaimGuestScore
{
    public function __construct(
        private PlayableGames $playableGames,
        private AnomalyPolicy $anomalies,
        private ScoreDistribution $distribution,
        private PersonalBests $personalBests,
        private Leaderboard $leaderboard,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $player, string $playId, string $token): ClaimedScore
    {
        if (! $player->hasVerifiedEmail()) {
            throw EmailNotVerified::forPlay();
        }

        try {
            [$score, $previousBest] = $this->db->transaction(fn (): array => $this->claim($player, $playId, $token));
        } catch (UniqueConstraintViolationException) {
            throw new GuestScoreAlreadyClaimed;
        }

        $recorded = new RecordedScore($score, $this->leaderboard->positionOf($score->game_id, $player->id), $previousBest);

        return new ClaimedScore($recorded, Game::query()->findOrFail($score->game_id));
    }

    /** @return array{Score, int|null} */
    private function claim(User $player, string $playId, string $token): array
    {
        $play = GuestPlay::query()->lockForUpdate()->find($playId) ?? throw new InvalidSessionToken;
        $play->authenticate($token);
        $this->playableGames->assertStillAcceptsScores($play->game_id, $play->game_version_id);

        $previousBest = $this->personalBests->of($play->game_id, $player->id);
        $flagReason = $play->value === null ? null : $this->anomalies->flagReason(ScoreValue::fromInt($play->value), $this->distribution->of($play->game_id));
        $session = $play->claim($player, $token, CarbonImmutable::now(), $flagReason);
        $this->publisher->publishFrom($session);

        return [$session->score()->sole(), $previousBest];
    }
}
