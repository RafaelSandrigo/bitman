<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use App\Modules\Identity\Domain\Role;
use App\Shared\Domain\BusinessRuleViolation;

final class RoleAlreadyGranted extends BusinessRuleViolation
{
    public static function for(Role $role): self
    {
        return new self("O usuário já tem a role {$role->value}.");
    }
}
