<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** S1: replay. Mapeada para 409. */
final class SessionAlreadyScored extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Esta partida já registrou um score. Inicie outra para jogar de novo.');
    }
}
