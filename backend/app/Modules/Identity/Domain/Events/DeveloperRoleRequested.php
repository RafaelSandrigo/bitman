<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** M18: o próprio usuário pediu para ser developer. */
final readonly class DeveloperRoleRequested implements Auditable
{
    public function __construct(
        public string $requestId,
        public string $userId,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'identity.developer-requested',
            subjectType: 'user',
            subjectId: $this->userId,
            actorId: $this->actorId,
            context: ['developer_request_id' => $this->requestId],
        );
    }
}
