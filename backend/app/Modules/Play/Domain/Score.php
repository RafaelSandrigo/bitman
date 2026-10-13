<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Events\ScoreHidden;
use App\Modules\Play\Domain\Events\ScoreRestored;
use App\Modules\Play\Domain\Exceptions\ScoreAlreadyHidden;
use App\Modules\Play\Domain\Exceptions\ScoreNotHidden;
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
 * Resultado de uma sessão. Nasce só por `GameSession::recordScore()`. O valor nunca muda; o admin
 * pode ocultá-lo do ranking (e devolvê-lo), sempre auditado. Sinalizado (S2) continua no ranking
 * até alguém decidir.
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
 * @property CarbonImmutable $created_at
 * @property-read User $player
 * @property-read Game $game
 * @property-read GameSession $session
 * @property-read GameVersion $version
 * @property-read User|null $hiddenBy
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
        $this->save();

        $this->recordEvent(new ScoreRestored($this->id, $this->game_id, $this->user_id, $this->value, $this->flagged, $admin->id));
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
            'created_at' => 'immutable_datetime',
        ];
    }
}
