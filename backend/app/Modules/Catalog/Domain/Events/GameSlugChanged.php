<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;
use App\Modules\Catalog\Domain\Slug;

final readonly class GameSlugChanged implements Auditable
{
    public function __construct(
        public string $gameId,
        public Slug $from,
        public Slug $to,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'catalog.slug-changed',
            subjectType: 'game',
            subjectId: $this->gameId,
            actorId: $this->actorId,
            context: ['from' => $this->from->value, 'to' => $this->to->value],
        );
    }
}
