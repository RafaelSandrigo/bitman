<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Events\ScoreRecorded;
use App\Modules\Play\Domain\Exceptions\InvalidSessionToken;
use App\Modules\Play\Domain\Exceptions\SessionAlreadyScored;
use App\Modules\Play\Domain\Exceptions\SessionClosed;
use App\Modules\Play\Domain\Exceptions\SessionExpired;
use App\Modules\Play\Domain\Exceptions\SessionTooShort;
use App\Shared\Domain\EmitsDomainEvents;
use App\Shared\Domain\RecordsDomainEvents;
use Carbon\CarbonImmutable;
use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Uma partida de um jogador numa versão publicada. Aceita no máximo um score (I10, S1): o token é
 * de uso único, e o banco ainda garante `unique(scores.game_session_id)`.
 *
 * @property string $id
 * @property string $user_id
 * @property string $game_id
 * @property string $game_version_id
 * @property string $token_hash
 * @property SessionStatus $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $ended_at
 * @property-read Score|null $score
 */
#[UseFactory(GameSessionFactory::class)]
final class GameSession extends Model implements EmitsDomainEvents
{
    /** @use HasFactory<GameSessionFactory> */
    use HasFactory, HasUlids, RecordsDomainEvents;

    public $timestamps = false;

    protected $guarded = ['*'];

    public static function start(User $player, string $gameId, string $versionId, SessionToken $token, PlayRules $rules, CarbonImmutable $now): self
    {
        $session = new self;
        $session->user_id = $player->id;
        $session->game_id = $gameId;
        $session->game_version_id = $versionId;
        $session->token_hash = $token->hash;
        $session->status = SessionStatus::Open;
        $session->started_at = $now;
        $session->expires_at = $now->addSeconds($rules->sessionTtlSeconds);
        $session->save();

        return $session;
    }

    /** A partida de visitante salva por uma conta: já nasce encerrada, com o início e o fim reais. */
    public static function fromGuestPlay(User $player, GuestPlay $play): self
    {
        $session = new self;
        $session->user_id = $player->id;
        $session->game_id = $play->game_id;
        $session->game_version_id = $play->game_version_id;
        $session->token_hash = $play->token_hash;
        $session->status = SessionStatus::Scored;
        $session->started_at = $play->started_at;
        $session->expires_at = $play->expires_at;
        $session->ended_at = $play->ended_at;
        $session->save();

        return $session;
    }

    /** Score de uma partida de visitante: as regras de I10 já foram conferidas em GuestPlay. */
    public function recordGuestScore(ScoreValue $value, ?string $flagReason, CarbonImmutable $now): Score
    {
        $score = Score::record($this, $value, $flagReason, $now);
        $this->recordEvent(new ScoreRecorded($score->id, $this->game_id, $this->user_id, $value->value, $score->flagged));

        return $score;
    }

    /** Outra partida do mesmo jogo começou: esta não aceita mais score. */
    public function expire(CarbonImmutable $now): void
    {
        if ($this->status !== SessionStatus::Open) {
            return;
        }

        $this->status = SessionStatus::Expired;
        $this->ended_at = $now;
        $this->save();
    }

    /**
     * I10: a sessão é do jogador, o token confere, está aberta, dentro do prazo e durou o mínimo.
     * O token é conferido antes de tudo: sem ele, nada sobre a sessão é revelado.
     */
    public function recordScore(User $player, string $token, ScoreValue $value, PlayRules $rules, CarbonImmutable $now, ?string $flagReason): Score
    {
        $this->authenticate($player, $token);

        match ($this->status) {
            SessionStatus::Scored => throw new SessionAlreadyScored,
            SessionStatus::Expired => throw new SessionClosed,
            SessionStatus::Open => null,
        };

        if ($now->greaterThanOrEqualTo($this->expires_at)) {
            throw new SessionExpired;
        }

        if ($this->started_at->diffInSeconds($now) < $rules->minSessionSeconds) {
            throw new SessionTooShort;
        }

        $this->status = SessionStatus::Scored;
        $this->ended_at = $now;
        $this->save();

        $score = Score::record($this, $value, $flagReason, $now);
        $this->recordEvent(new ScoreRecorded($score->id, $this->game_id, $this->user_id, $value->value, $score->flagged));

        return $score;
    }

    /** A sessão é do jogador e o token confere; senão, nada sobre ela é revelado. */
    public function authenticate(User $player, string $token): void
    {
        if ($this->user_id !== $player->id || ! hash_equals($this->token_hash, SessionToken::hashOf($token))) {
            throw new InvalidSessionToken;
        }
    }

    /** @return HasOne<Score, $this> */
    public function score(): HasOne
    {
        return $this->hasOne(Score::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'started_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
