<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Arquivo candidato de uma versão: um path da allowlist + o SHA-256 do conteúdo na quarentena.
 * Só nasce pelo `GameVersion::completeFetch()`. O `review_status` é o da última decisão do Portão 2
 * (`FileReview`, append-only).
 *
 * @property string $id
 * @property string $game_version_id
 * @property string $path
 * @property string $sha256
 * @property int $size
 * @property string $git_blob_sha
 * @property ChangeType $change_type
 * @property FileReviewStatus $review_status
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read GameVersion $version
 * @property-read Collection<int, FileReview> $reviews
 */
final class VersionFile extends Model
{
    use HasUlids;

    protected $guarded = ['*'];

    public static function record(GameVersion $version, QuarantinedFile $file): self
    {
        $record = new self;
        $record->game_version_id = $version->id;
        $record->path = $file->path;
        $record->sha256 = $file->sha256;
        $record->size = $file->size;
        $record->git_blob_sha = $file->gitBlobSha;
        $record->change_type = $file->changeType;
        $record->review_status = FileReviewStatus::Pending;
        $record->save();

        return $record;
    }

    /** Só pelo `GameVersion::reviewFile()`, junto com o `FileReview` que registra a decisão. */
    public function markReviewed(FileReviewStatus $status): void
    {
        $this->review_status = $status;
        $this->save();
    }

    /** @return HasMany<FileReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(FileReview::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return BelongsTo<GameVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class, 'game_version_id');
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'change_type' => ChangeType::class,
            'review_status' => FileReviewStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
