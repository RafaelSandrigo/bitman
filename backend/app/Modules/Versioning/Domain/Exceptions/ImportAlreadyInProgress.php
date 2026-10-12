<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class ImportAlreadyInProgress extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Este jogo já tem uma versão em andamento. Conclua ou rejeite essa versão antes de pedir outra importação.');
    }
}
