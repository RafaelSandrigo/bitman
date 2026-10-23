<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\PersonalBests;
use App\Modules\Play\Application\PlayableGames;
use App\Modules\Play\Application\RecordedScore;
use App\Modules\Play\Domain\AnomalyPolicy;
use App\Modules\Play\Domain\Exceptions\InvalidSessionToken;
use App\Modules\Play\Domain\Exceptions\SessionAlreadyScored;
use App\Modules\Play\Domain\GameSession;
use App\Modules\Play\Domain\Leaderboard;
use App\Modules\Play\Domain\PlayRules;
use App\Modules\Play\Domain\ScoreDistribution;
use App\Modules\Play\Domain\ScoreValue;
use App\Shared\Application\DomainEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Registra o score de uma sessão (I10, S1, S2). A sessão é travada na transação, então envios
 * concorrentes resultam em exatamente um registro; o `unique(game_session_id)` é a última barreira.
 */
final readonly class SubmitScore
{
    public function __construct(
        private PlayRules $rules,
        private PlayableGames $playableGames,
        private PersonalBests $personalBests,
        private AnomalyPolicy $anomalies,
        private ScoreDistribution $distribution,
        private Leaderboard $leaderboard,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $player, string $sessionId, string $token, ScoreValue $value): RecordedScore
    {
        try {
            [$score, $previousBest] = $this->db->transaction(function () use ($player, $sessionId, $token, $value): array {
                $session = GameSession::query()->lockForUpdate()->find($sessionId) ?? throw new InvalidSessionToken;
                $session->authenticate($player, $token);
                $this->playableGames->assertStillAcceptsScores($session->game_id, $session->game_version_id);

                $previousBest = $this->personalBests->of($session->game_id, $player->id);
                $flagReason = $this->anomalies->flagReason(
                    $value,
                    $this->distribution->of($session->game_id),
                    (int) $session->started_at->diffInSeconds(CarbonImmutable::now()),
                    $this->playableGames->scoreCapOf($session->game_id),
                );

                $score = $session->recordScore($player, $token, $value, $this->rules, CarbonImmutable::now(), $flagReason);
                $this->publisher->publishFrom($session);

                return [$score, $previousBest];
            });
        } catch (UniqueConstraintViolationException) {
            throw new SessionAlreadyScored;
        }

        return new RecordedScore($score, $this->leaderboard->positionOf($score->game_id, $player->id), $previousBest);
    }
}
