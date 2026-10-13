<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** O token não confere com o da sessão (I10). */
final class InvalidSessionToken extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Sessão de jogo inválida.');
    }
}
