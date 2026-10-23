<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** S1: o score pendente de uma partida de visitante já virou Score (de qualquer conta). */
final class GuestScoreAlreadyClaimed extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Este score já foi salvo.');
    }
}
