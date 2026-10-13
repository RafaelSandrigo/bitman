<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** S2: fora da faixa, fracionário ou não numérico; nunca é arredondado. */
final class InvalidScore extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('O score precisa ser um inteiro entre 0 e 9.007.199.254.740.991.');
    }
}
