<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;
use App\Modules\Catalog\Domain\Slug;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;

/** Contrato público do Catalog: o Versioning reage criando a versão 1 (`import_requested`). */
final readonly class GameSubmitted implements Auditable
{
    public function __construct(
        public string $gameId,
        public string $ownerId,
        public Slug $slug,
        public RepositoryUrl $repositoryUrl,
        public GitRef $ref,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'catalog.game-submitted',
            subjectType: 'game',
            subjectId: $this->gameId,
            actorId: $this->ownerId,
            context: ['slug' => $this->slug->value, 'repository_url' => $this->repositoryUrl->value, 'ref' => $this->ref->value],
        );
    }
}
