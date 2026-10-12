<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;
use App\Modules\Versioning\Domain\FileDecision;

/** Portão 2: responde "quem aprovou qual hash, quando" (I5, I11). */
final readonly class FileReviewed implements Auditable
{
    public function __construct(
        public string $versionId,
        public string $fileId,
        public string $path,
        public string $sha256,
        public FileDecision $decision,
        public string $actorId,
        public ?string $reason,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: $this->decision === FileDecision::Approved ? 'versioning.file-approved' : 'versioning.file-rejected',
            subjectType: 'game_version',
            subjectId: $this->versionId,
            actorId: $this->actorId,
            context: ['file_id' => $this->fileId, 'path' => $this->path, 'sha256' => $this->sha256],
            reason: $this->reason,
        );
    }
}
