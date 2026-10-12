<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use App\Modules\Versioning\Domain\VersionStatus;
use App\Shared\Domain\BusinessRuleViolation;

/** A ação não vale para o estado atual da versão (ex.: autorizar duas vezes). */
final class InvalidVersionTransition extends BusinessRuleViolation
{
    public static function from(VersionStatus $current, string $action): self
    {
        return new self("A versão está {$current->label()} e não pode {$action}.");
    }
}
