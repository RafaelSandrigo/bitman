<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** A partida de visitante não tem um score que ainda possa ser salvo. */
final class GuestScoreUnavailable extends BusinessRuleViolation
{
    public static function notScored(): self
    {
        return new self('Esta partida não tem score para salvar.');
    }

    public static function claimExpired(): self
    {
        return new self('O prazo para salvar este score acabou. Jogue de novo para entrar no ranking.');
    }
}
