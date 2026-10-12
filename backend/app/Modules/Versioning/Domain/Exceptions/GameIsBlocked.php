<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class GameIsBlocked extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Este jogo está bloqueado. Novas versões só depois do desbloqueio.');
    }
}
