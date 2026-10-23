<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Exceptions\GuestScoreAlreadyClaimed;
use App\Modules\Play\Domain\Exceptions\GuestScoreUnavailable;
use App\Modules\Play\Domain\Exceptions\InvalidSessionToken;
use App\Modules\Play\Domain\Exceptions\SessionAlreadyScored;
use App\Modules\Play\Domain\Exceptions\SessionExpired;
use App\Modules\Play\Domain\Exceptions\SessionTooShort;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Partida de quem não está logado (ou ainda não confirmou o e-mail). Segue as regras da partida
 * (I10: token, prazo, duração mínima, um score), mas o score fica pendente: só entra no ranking
 * quando uma conta confirmada o salva com o mesmo token, dentro de `claim_expires_at` (A5, S1).
 * Salvar devolve a GameSession criada, que carrega o evento ScoreRecorded.
 *
 * @property string $id
 * @property string $game_id
 * @property string $game_version_id
 * @property string $token_hash
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $expires_at
 * @property int|null $value
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable|null $claim_expires_at
 * @property string|null $claimed_score_id
 */
final class GuestPlay extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = ['*'];

    public static function start(string $gameId, string $versionId, SessionToken $token, PlayRules $rules, CarbonImmutable $now): self
    {
        $play = new self;
        $play->game_id = $gameId;
        $play->game_version_id = $versionId;
        $play->token_hash = $token->hash;
        $play->started_at = $now;
        $play->expires_at = $now->addSeconds($rules->sessionTtlSeconds);
        $play->save();

        return $play;
    }

    /** I10 como na partida valendo score; o valor fica guardado à espera de uma conta. */
    public function recordScore(string $token, ScoreValue $value, PlayRules $rules, CarbonImmutable $now): void
    {
        $this->authenticate($token);

        if ($this->value !== null) {
            throw new SessionAlreadyScored;
        }
        if ($now->greaterThanOrEqualTo($this->expires_at)) {
            throw new SessionExpired;
        }
        if ($this->started_at->diffInSeconds($now) < $rules->minSessionSeconds) {
            throw new SessionTooShort;
        }

        $this->value = $value->value;
        $this->ended_at = $now;
        $this->claim_expires_at = $now->addSeconds($rules->guestClaimTtlSeconds);
        $this->save();
    }

    /**
     * Transforma o score pendente numa partida e num Score do jogador. A sessão criada guarda o
     * início e o fim reais da partida, não o momento em que a conta foi criada.
     */
    public function claim(User $player, string $token, CarbonImmutable $now, ?string $flagReason): GameSession
    {
        $this->assertClaimable($token, $now);

        $session = GameSession::fromGuestPlay($player, $this);
        $score = $session->recordGuestScore(ScoreValue::fromInt((int) $this->value), $flagReason, $now);

        $this->claimed_score_id = $score->id;
        $this->save();

        return $session;
    }

    public function authenticate(string $token): void
    {
        if (! hash_equals($this->token_hash, SessionToken::hashOf($token))) {
            throw new InvalidSessionToken;
        }
    }

    private function assertClaimable(string $token, CarbonImmutable $now): void
    {
        $this->authenticate($token);

        if ($this->claimed_score_id !== null) {
            throw new GuestScoreAlreadyClaimed;
        }
        if ($this->value === null || $this->claim_expires_at === null) {
            throw GuestScoreUnavailable::notScored();
        }
        if ($now->greaterThanOrEqualTo($this->claim_expires_at)) {
            throw GuestScoreUnavailable::claimExpired();
        }
    }

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'started_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'claim_expires_at' => 'immutable_datetime',
        ];
    }
}
