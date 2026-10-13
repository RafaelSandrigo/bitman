<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** A sessão expirou ao começar outra partida do mesmo jogo. */
final class SessionClosed extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Esta partida foi encerrada porque outra começou. Inicie outra para jogar de novo.');
    }
}
