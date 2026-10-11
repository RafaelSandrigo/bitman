<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Events;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditRecord;
use App\Modules\Identity\Domain\Role;

final readonly class RoleGranted implements Auditable
{
    /** @param  string|null  $actorId  null quando concedida pelo console (bootstrap do primeiro admin) */
    public function __construct(
        public string $userId,
        public Role $role,
        public ?string $actorId,
    ) {}

    public function toAuditRecord(): AuditRecord
    {
        return new AuditRecord(
            action: 'identity.role-granted',
            subjectType: 'user',
            subjectId: $this->userId,
            actorId: $this->actorId,
            context: ['role' => $this->role->value, 'via' => $this->actorId === null ? 'console' : 'panel'],
        );
    }
}
