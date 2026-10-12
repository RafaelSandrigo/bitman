<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** I6: todos os arquivos obrigatórios aprovados. */
final class VersionNotReadyToPublish extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('A versão só pode ser publicada com todos os arquivos aprovados.');
    }
}
