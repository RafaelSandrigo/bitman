<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

/** M20: só um score sinalizado, ainda sem decisão do admin, pode ser mantido no ranking. */
final class ScoreNotInReview extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('Este score não está em revisão.');
    }
}
