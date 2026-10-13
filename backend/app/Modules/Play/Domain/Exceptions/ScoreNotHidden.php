<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class ScoreNotHidden extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Este score não está oculto.');
    }
}
