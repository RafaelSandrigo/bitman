<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use App\Modules\Versioning\Domain\DecisionReason;
use App\Shared\Domain\BusinessRuleViolation;

final class InvalidDecisionReason extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct(sprintf('Informe o motivo, de %d a %d caracteres.', DecisionReason::MIN, DecisionReason::MAX));
    }
}
