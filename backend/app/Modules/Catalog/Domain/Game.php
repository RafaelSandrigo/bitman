<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

use App\Modules\Catalog\Domain\Events\GameSlugChanged;
use App\Modules\Catalog\Domain\Events\GameSubmitted;
use App\Modules\Catalog\Domain\Exceptions\SlugIsImmutable;
use App\Modules\Identity\Domain\User;
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

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
