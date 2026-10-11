<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class PlayerRoleIsPermanent extends BusinessRuleViolation
{
    public static function create(): self
    {
        return new self('A role player é a base de toda conta e não pode ser removida.');
    }
}
