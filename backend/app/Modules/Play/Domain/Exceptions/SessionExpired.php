<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** I10: depois de expires_at, a sessão não aceita score. */
final class SessionExpired extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('O prazo desta partida acabou. Inicie outra para jogar de novo.');
    }
}
