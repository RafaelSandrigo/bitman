<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Resources;

use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionFile;
use Illuminate\Http\Request;

/**
 * Versão com a decisão do Portão 0, o relatório da política e os arquivos candidatos.
 *
 * @mixin GameVersion
 */
final class VersionDetailResource extends VersionResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'import_decision' => $this->import_decided_at === null ? null : [
                'decided_by' => $this->importDecidedBy === null ? null : ['id' => $this->importDecidedBy->id, 'name' => $this->importDecidedBy->name],
                'decided_at' => $this->import_decided_at->toIso8601String(),
                'rejection_reason' => $this->import_rejection_reason,
            ],
            'report' => $this->validation_report,
            'files' => $this->whenLoaded('files', fn (): array => $this->files->map(fn (VersionFile $file): array => [
                'id' => $file->id,
                'path' => $file->path,
                'sha256' => $file->sha256,
                'size' => $file->size,
                'git_blob_sha' => $file->git_blob_sha,
                'change_type' => $file->change_type->value,
                'review_status' => $file->review_status->value,
            ])->all()),
        ];
    }
}
