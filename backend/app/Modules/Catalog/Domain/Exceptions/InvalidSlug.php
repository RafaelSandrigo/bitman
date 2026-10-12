<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class InvalidSlug extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('O slug deve ter de 2 a 60 caracteres: letras minúsculas e números, separados por hífens.');
    }
}
