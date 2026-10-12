<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class FileContentUnavailable extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('O conteúdo só fica disponível enquanto a versão está em revisão.');
    }
}
