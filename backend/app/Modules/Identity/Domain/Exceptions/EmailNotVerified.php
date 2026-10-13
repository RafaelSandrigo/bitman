<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class EmailNotVerified extends BusinessRuleViolation
{
    public static function forRoleGrant(): self
    {
        return new self('O usuário precisa verificar o e-mail antes de receber esta role.');
    }

    /** A5: contas descartáveis não entram no ranking. */
    public static function forPlay(): self
    {
        return new self('Confirme seu e-mail para jogar valendo score.');
    }
}
