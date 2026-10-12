<?php

declare(strict_types=1);

namespace App\Shared\Domain\Git;

use App\Shared\Domain\BusinessRuleViolation;

final class InvalidRepositoryUrl extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Use o endereço HTTPS de um repositório público no GitHub, GitLab ou Codeberg.');
    }
}
