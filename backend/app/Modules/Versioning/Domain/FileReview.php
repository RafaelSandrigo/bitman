<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

use App\Modules\Identity\Domain\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma decisão do Portão 2: quem decidiu, sobre qual hash, quando e por quê. Append-only (trigger
 * no banco); o estado atual do arquivo é o da última decisão. Só nasce pelo `GameVersion::reviewFile()`.
 *
 * @property string $id
 * @property string $version_file_id
 * @property string $sha256
 * @property FileDecision $decision
 * @property string $reviewer_id
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 * @property-read VersionFile $file
 * @property-read User $reviewer
 */
final class FileReview extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    public static function record(VersionFile $file, FileDecision $decision, User $reviewer, ?DecisionReason $reason): self
    {
        $review = new self;
        $review->version_file_id = $file->id;
        $review->sha256 = $file->sha256;
        $review->decision = $decision;
        $review->reviewer_id = $reviewer->id;
        $review->reason = $reason?->value;
        $review->save();

        return $review;
    }

    /** @return BelongsTo<VersionFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(VersionFile::class, 'version_file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    protected function casts(): array
    {
        return [
            'decision' => FileDecision::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
