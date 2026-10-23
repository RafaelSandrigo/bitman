<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** M18: um pedido pendente por usuário. */
final class DeveloperRequestAlreadyOpen extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Você já tem um pedido em análise.');
    }
}
