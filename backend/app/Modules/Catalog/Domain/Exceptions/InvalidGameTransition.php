<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class InvalidGameTransition extends BusinessRuleViolation
{
    public static function cannot(string $action): self
    {
        return new self("O jogo não pode {$action} no estado atual.");
    }
}
