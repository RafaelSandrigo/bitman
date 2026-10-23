<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** M18: o link do GitHub precisa ser um perfil (https://github.com/usuário). */
final class InvalidGithubProfileUrl extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Informe o endereço do seu perfil no GitHub, no formato https://github.com/seu-usuario.');
    }
}
