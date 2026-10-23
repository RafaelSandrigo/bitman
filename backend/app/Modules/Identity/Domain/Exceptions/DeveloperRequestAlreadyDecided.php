<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** M18: aprovar ou rejeitar só um pedido pendente. */
final class DeveloperRequestAlreadyDecided extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Este pedido já foi decidido.');
    }
}
