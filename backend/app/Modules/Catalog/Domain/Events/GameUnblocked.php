<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** O jogo volta ao ar com a mesma versão publicada, servida de novo a partir do arquivo permanente. */
final readonly class GameUnblocked implements Auditable
{
    public function __construct(
        public string $gameId,
        public ?string $publishedVersionId,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'catalog.game-unblocked',
            subjectType: 'game',
            subjectId: $this->gameId,
            actorId: $this->actorId,
            context: ['published_version_id' => $this->publishedVersionId],
        );
    }
}
