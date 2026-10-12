<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;

final readonly class ImportRequested implements Auditable
{
    public function __construct(
        public string $versionId,
        public string $gameId,
        public int $number,
        public RepositoryUrl $repositoryUrl,
        public GitRef $ref,
        public string $requestedById,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'versioning.import-requested',
            subjectType: 'game_version',
            subjectId: $this->versionId,
            actorId: $this->requestedById,
            context: [
                'game_id' => $this->gameId,
                'number' => $this->number,
                'repository_url' => $this->repositoryUrl->value,
                'ref' => $this->ref->value,
            ],
        );
    }
}
