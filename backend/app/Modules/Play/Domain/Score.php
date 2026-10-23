<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Events\ScoreHidden;
use App\Modules\Play\Domain\Events\ScoreKept;
use App\Modules\Play\Domain\Events\ScoreRestored;
use App\Modules\Play\Domain\Exceptions\ScoreAlreadyHidden;
use App\Modules\Play\Domain\Exceptions\ScoreNotHidden;
use App\Modules\Play\Domain\Exceptions\ScoreNotInReview;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\GameVersion;
use App\Shared\Domain\EmitsDomainEvents;
use App\Shared\Domain\RecordsDomainEvents;
use Carbon\CarbonImmutable;
use Database\Factories\ScoreFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado de uma sessão. Nasce só por `GameSession::recordScore()` ou, para a partida de visitante
 * salva numa conta, por `GameSession::recordGuestScore()`. O valor nunca muda; o admin
 * pode ocultá-lo do ranking (e devolvê-lo), sempre auditado. Sinalizado (S2) fica fora do ranking,
 * em revisão, até o admin mantê-lo (`keep()`) ou ocultá-lo (M20).
 *
 * @property string $id
 * @property string $game_session_id
 * @property string $user_id
 * @property string $game_id
 * @property string $game_version_id
 * @property int $value
 * @property bool $flagged
 * @property string|null $flag_reason
 * @property string|null $hidden_by_id
 * @property CarbonImmutable|null $hidden_at
 * @property string|null $hide_reason
 * @property string|null $flag_cleared_by_id
 * @property CarbonImmutable|null $flag_cleared_at
 * @property CarbonImmutable $created_at
 * @property-read User $player
 * @property-read Game $game
 * @property-read GameSession $session
 * @property-read GameVersion $version
 * @property-read User|null $hiddenBy
 * @property-read User|null $flagClearedBy
 */
#[UseFactory(ScoreFactory::class)]
final class Score extends Model implements EmitsDomainEvents
{
    /** @use HasFactory<ScoreFactory> */
    use HasFactory, HasUlids, RecordsDomainEvents;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    public static function record(GameSession $session, ScoreValue $value, ?string $flagReason, CarbonImmutable $now): self
    {
        $score = new self;
        $score->game_session_id = $session->id;
        $score->user_id = $session->user_id;
        $score->game_id = $session->game_id;
        $score->game_version_id = $session->game_version_id;
        $score->value = $value->value;
        $score->flagged = $flagReason !== null;
        $score->flag_reason = $flagReason;
        $score->created_at = $now;
        $score->save();

        return $score;
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /** Sinalizado, sem decisão do admin: fora do ranking até ser mantido ou oculto. */
    public function isInReview(): bool
    {
        return $this->flagged && $this->flag_cleared_at === null && ! $this->isHidden();
    }

    /** M20: o admin conferiu o sinalizado e o mantém no ranking. */
    public function keep(User $admin): void
    {
        if (! $this->isInReview()) {
            throw new ScoreNotInReview;
        }

        $this->clearFlag($admin);
        $this->save();

        $this->recordEvent(new ScoreKept($this->id, $this->game_id, $this->user_id, $this->value, $admin->id));
    }

    public function hide(User $admin, DecisionReason $reason): void
    {
        if ($this->isHidden()) {
            throw new ScoreAlreadyHidden;
        }

        $this->hidden_by_id = $admin->id;
        $this->hidden_at = CarbonImmutable::now();
        $this->hide_reason = $reason->value;
        $this->save();

        $this->recordEvent(new ScoreHidden($this->id, $this->game_id, $this->user_id, $this->value, $this->flagged, $admin->id, $reason->value));
    }

    public function restore(User $admin): void
    {
        if (! $this->isHidden()) {
            throw new ScoreNotHidden;
        }

        $this->hidden_by_id = null;
        $this->hidden_at = null;
        $this->hide_reason = null;
        // Devolver ao ranking é a decisão sobre o sinalizado: ele não volta para a revisão.
        if ($this->flagged && $this->flag_cleared_at === null) {
            $this->clearFlag($admin);
        }
        $this->save();

        $this->recordEvent(new ScoreRestored($this->id, $this->game_id, $this->user_id, $this->value, $this->flagged, $admin->id));
    }

    private function clearFlag(User $admin): void
    {
        $this->flag_cleared_by_id = $admin->id;
        $this->flag_cleared_at = CarbonImmutable::now();
    }

    /** @return BelongsTo<User, $this> */
    public function flagClearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flag_cleared_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<GameSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(GameSession::class, 'game_session_id');
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<GameVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class, 'game_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by_id');
    }

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'flagged' => 'boolean',
            'hidden_at' => 'immutable_datetime',
            'flag_cleared_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
