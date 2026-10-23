<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;

/** M18: o admin aprovou o pedido (a role vem pelo RoleGranted). */
final readonly class DeveloperRequestApproved implements Auditable
{
    public function __construct(
        public string $requestId,
        public string $userId,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'identity.developer-request-approved',
            subjectType: 'user',
            subjectId: $this->userId,
            actorId: $this->actorId,
            context: ['developer_request_id' => $this->requestId],
        );
    }
}
