<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** M18: quem já é developer não abre pedido. */
final class AlreadyDeveloper extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Você já é developer.');
    }
}
