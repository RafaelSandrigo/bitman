<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Resources;

use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\VersionDetails;
use App\Modules\Versioning\Domain\FileReview;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Versão com as decisões de cada portão, o relatório da política e os arquivos candidatos, cada
 * um com o histórico de decisões (append-only) e a aprovação anterior do mesmo conteúdo (ADR 0006).
 *
 * @mixin GameVersion
 */
final class VersionDetailResource extends VersionResource
{
    /** @var array<string, FileReview> */
    private array $previousApprovals;

    public function __construct(VersionDetails $details)
    {
        parent::__construct($details->version);
        $this->previousApprovals = $details->previousApprovals;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'import_decision' => self::decision($this->importDecidedBy, $this->import_decided_at, $this->import_rejection_reason),
            'commit_decision' => self::decision($this->commitDecidedBy, $this->commit_decided_at, $this->commit_rejection_reason),
            'rejection' => self::decision($this->rejectedBy, $this->rejected_at, $this->rejection_reason),
            'publication' => self::decision($this->publishedBy, $this->published_at, null),
            'withdrawal' => self::decision($this->withdrawnBy, $this->withdrawn_at, $this->withdrawal_reason),
            'play_url' => $this->status === VersionStatus::Published && $this->public_id !== null
                ? config('publication.games_origin')."/{$this->public_id}/index.html"
                : null,
            'report' => $this->validation_report,
            'files' => $this->files->map(fn (VersionFile $file): array => [
                'id' => $file->id,
                'path' => $file->path,
                'sha256' => $file->sha256,
                'size' => $file->size,
                'git_blob_sha' => $file->git_blob_sha,
                'change_type' => $file->change_type->value,
                'review_status' => $file->review_status->value,
                'reviews' => $file->reviews->map(fn (FileReview $review): array => self::review($review))->all(),
                'previous_approval' => isset($this->previousApprovals[$file->id])
                    ? [...self::review($this->previousApprovals[$file->id]), 'version_number' => $this->previousApprovals[$file->id]->file->version->number]
                    : null,
            ])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function decision(?User $by, ?CarbonImmutable $at, ?string $rejectionReason): ?array
    {
        return $at === null ? null : [
            'decided_by' => $by === null ? null : ['id' => $by->id, 'name' => $by->name],
            'decided_at' => $at->toIso8601String(),
            'rejection_reason' => $rejectionReason,
        ];
    }

    /** @return array<string, mixed> */
    private static function review(FileReview $review): array
    {
        return [
            'id' => $review->id,
            'decision' => $review->decision->value,
            'sha256' => $review->sha256,
            'reason' => $review->reason,
            'reviewer' => ['id' => $review->reviewer->id, 'name' => $review->reviewer->name],
            'decided_at' => $review->created_at->toIso8601String(),
        ];
    }
}
