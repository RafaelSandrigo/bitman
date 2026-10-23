<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

use App\Modules\Catalog\Domain\Events\GameBlocked;
use App\Modules\Catalog\Domain\Events\GameSlugChanged;
use App\Modules\Catalog\Domain\Events\GameSubmitted;
use App\Modules\Catalog\Domain\Events\GameUnblocked;
use App\Modules\Catalog\Domain\Events\ScoreCapChanged;
use App\Modules\Catalog\Domain\Exceptions\InvalidGameTransition;
use App\Modules\Catalog\Domain\Exceptions\SlugIsImmutable;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Shared\Domain\EmitsDomainEvents;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;
use App\Shared\Domain\RecordsDomainEvents;
use Carbon\CarbonImmutable;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agregado do catálogo. Nada é preenchível em massa: o jogo nasce por `submit()` e muda só
 * pelos métodos de intenção, que registram o evento auditável.
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property string $author_name
 * @property string $thumbnail_path nome do arquivo gerado pelo ThumbnailProcessor
 * @property string $owner_id
 * @property string $repository_url
 * @property string $default_ref
 * @property GameStatus $status
 * @property string|null $published_version_id a versão servida agora (I8, I9)
 * @property CarbonImmutable|null $first_published_at
 * @property string|null $blocked_by_id
 * @property CarbonImmutable|null $blocked_at
 * @property string|null $block_reason
 * @property int|null $score_cap teto de score (M15); acima dele, o score é sinalizado
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $owner
 */
#[UseFactory(GameFactory::class)]
final class Game extends Model implements EmitsDomainEvents
{
    /** @use HasFactory<GameFactory> */
    use HasFactory, HasUlids, RecordsDomainEvents;

    protected $guarded = ['*'];

    public static function submit(
        User $owner,
        string $name,
        string $description,
        string $authorName,
        Slug $slug,
        string $thumbnailPath,
        RepositoryUrl $repositoryUrl,
        GitRef $defaultRef,
    ): self {
        $game = new self;
        $game->owner_id = $owner->id;
        $game->name = $name;
        $game->description = $description;
        $game->author_name = $authorName;
        $game->slug = $slug->value;
        $game->thumbnail_path = $thumbnailPath;
        $game->repository_url = $repositoryUrl->value;
        $game->default_ref = $defaultRef->value;
        $game->status = GameStatus::Submitted;
        $game->save();

        $game->recordEvent(new GameSubmitted($game->id, $owner->id, $slug, $repositoryUrl, $defaultRef));

        return $game;
    }

    /** O admin ajusta o slug até a primeira publicação; depois ele é imutável. */
    /** M15: `null` remove o teto. Sem mudança, nada é registrado. */
    public function changeScoreCap(?int $cap, User $actor): void
    {
        $from = $this->score_cap;
        if ($from === $cap) {
            return;
        }

        $this->score_cap = $cap;
        $this->save();

        $this->recordEvent(new ScoreCapChanged($this->id, $from, $cap, $actor->id));
    }

    public function changeSlug(Slug $slug, User $actor): void
    {
        if ($this->status->wasPublished()) {
            throw new SlugIsImmutable;
        }

        $current = Slug::fromString($this->slug);
        if ($current->equals($slug)) {
            return;
        }

        $this->slug = $slug->value;
        $this->save();

        $this->recordEvent(new GameSlugChanged($this->id, $current, $slug, $actor->id));
    }

    /**
     * Uma versão foi publicada (evento do Versioning, na mesma transação). A primeira publicação
     * fixa a URL pública: o slug passa a ser imutável.
     */
    public function recordPublication(string $versionId): void
    {
        if ($this->status === GameStatus::Blocked) {
            throw InvalidGameTransition::cannot('receber uma publicação enquanto está bloqueado');
        }

        $this->published_version_id = $versionId;
        $this->first_published_at ??= CarbonImmutable::now();
        $this->status = GameStatus::Published;
        $this->save();
    }

    /** A versão publicada foi retirada: o jogo fica sem versão jogável até outra ser publicada (I9). */
    public function clearPublication(string $versionId): void
    {
        if ($this->published_version_id === $versionId) {
            $this->published_version_id = null;
            $this->save();
        }
    }

    /** J6: tira o jogo do ar, com motivo (I11). */
    public function block(User $actor, DecisionReason $reason): void
    {
        if ($this->status !== GameStatus::Published) {
            throw InvalidGameTransition::cannot('ser bloqueado');
        }

        $this->status = GameStatus::Blocked;
        $this->blocked_by_id = $actor->id;
        $this->blocked_at = CarbonImmutable::now();
        $this->block_reason = $reason->value;
        $this->save();

        $this->recordEvent(new GameBlocked($this->id, $this->published_version_id, $actor->id, $reason->value));
    }

    public function unblock(User $actor): void
    {
        if ($this->status !== GameStatus::Blocked) {
            throw InvalidGameTransition::cannot('ser desbloqueado');
        }

        $this->status = GameStatus::Published;
        $this->blocked_by_id = null;
        $this->blocked_at = null;
        $this->block_reason = null;
        $this->save();

        $this->recordEvent(new GameUnblocked($this->id, $this->published_version_id, $actor->id));
    }

    /** I9: aparece na listagem e pode ser jogado. */
    public function isPlayable(): bool
    {
        return $this->status === GameStatus::Published && $this->published_version_id !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'first_published_at' => 'immutable_datetime',
            'score_cap' => 'integer',
            'blocked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
