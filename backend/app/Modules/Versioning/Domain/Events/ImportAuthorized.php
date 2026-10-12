<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** Portão 0 aprovado: a partir daqui o sistema pode acessar o repositório (I2). */
final readonly class ImportAuthorized implements Auditable
{
    public function __construct(
        public string $versionId,
        public string $gameId,
        public int $number,
        public string $repositoryUrl,
        public string $ref,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'versioning.import-authorized',
            subjectType: 'game_version',
            subjectId: $this->versionId,
            actorId: $this->actorId,
            context: ['game_id' => $this->gameId, 'number' => $this->number, 'repository_url' => $this->repositoryUrl, 'ref' => $this->ref],
        );
    }
}
