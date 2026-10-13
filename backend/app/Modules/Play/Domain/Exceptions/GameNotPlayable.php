<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** I9: só jogo publicado, não bloqueado, com a versão no ar. */
final class GameNotPlayable extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Este jogo não está disponível para partidas no momento.');
    }
}
