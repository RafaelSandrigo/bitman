<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;
use App\Modules\Identity\Domain\Role;

final readonly class RoleRevoked implements Auditable
{
    public function __construct(
        public string $userId,
        public Role $role,
        public string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'identity.role-revoked',
            subjectType: 'user',
            subjectId: $this->userId,
            actorId: $this->actorId,
            context: ['role' => $this->role->value, 'via' => 'panel'],
        );
    }
}
