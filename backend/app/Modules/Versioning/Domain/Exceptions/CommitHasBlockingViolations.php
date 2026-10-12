<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** I3: o Portão 1 nunca aprova um relatório com violação bloqueante. */
final class CommitHasBlockingViolations extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('O relatório tem violações bloqueantes: o commit não pode ser aprovado.');
    }
}
