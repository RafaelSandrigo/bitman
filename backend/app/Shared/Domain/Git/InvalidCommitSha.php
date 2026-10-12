<?php

declare(strict_types=1);

namespace App\Shared\Domain\Git;

use App\Shared\Domain\BusinessRuleViolation;

final class InvalidCommitSha extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('SHA de commit inválido.');
    }
}
