<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\RecordedScore;
use App\Modules\Play\Domain\AnomalyPolicy;
use App\Modules\Play\Domain\Exceptions\GameNotPlayable;
use App\Modules\Play\Domain\Exceptions\InvalidSessionToken;
use App\Modules\Play\Domain\Exceptions\SessionAlreadyScored;
use App\Modules\Play\Domain\GameSession;
use App\Modules\Play\Domain\Leaderboard;
use App\Modules\Play\Domain\PlayRules;
use App\Modules\Play\Domain\Score;
use App\Modules\Play\Domain\ScoreDistribution;
use App\Modules\Play\Domain\ScoreValue;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
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
                $this->assertStillPlayable($session);

                $previousBest = Score::query()
                    ->where('game_id', $session->game_id)
                    ->where('user_id', $player->id)
                    ->whereNull('hidden_at')
                    ->max('value');
                $flagReason = $this->anomalies->flagReason($value, $this->distribution->of($session->game_id));

                $score = $session->recordScore($player, $token, $value, $this->rules, CarbonImmutable::now(), $flagReason);
                $this->publisher->publishFrom($session);

                return [$score, $previousBest === null ? null : (int) $previousBest];
            });
        } catch (UniqueConstraintViolationException) {
            throw new SessionAlreadyScored;
        }

        return new RecordedScore($score, $this->leaderboard->positionOf($score->game_id, $player->id), $previousBest);
    }

    /**
     * Jogo bloqueado ou versão retirada durante a partida: o score não entra. Versão substituída
     * por uma nova durante a partida continua valendo (o ranking considera todas as versões).
     */
    private function assertStillPlayable(GameSession $session): void
    {
        $game = Game::query()->find($session->game_id);
        $version = GameVersion::query()->find($session->game_version_id);

        if ($game?->status !== GameStatus::Published
            || ! in_array($version?->status, [VersionStatus::Published, VersionStatus::Superseded], true)) {
            throw new GameNotPlayable;
        }
    }
}
