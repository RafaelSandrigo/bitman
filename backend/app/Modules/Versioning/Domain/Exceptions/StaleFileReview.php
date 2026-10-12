<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** I5: a decisão vale para o hash analisado. Outro hash significa que o admin não viu este conteúdo. */
final class StaleFileReview extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('O conteúdo analisado não é o deste arquivo (hash diferente). Abra o arquivo de novo antes de decidir.');
    }
}
