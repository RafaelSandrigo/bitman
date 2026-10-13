<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** I10: duração mínima. */
final class SessionTooShort extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('A partida durou menos que o mínimo para registrar o score.');
    }
}
