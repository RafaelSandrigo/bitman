<?php

declare(strict_types=1);

namespace App\Shared\Domain\Git;

use App\Shared\Domain\BusinessRuleViolation;

final class InvalidGitRef extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Informe uma branch, tag ou commit válido: letras, números e os símbolos ". _ - /".');
    }
}
