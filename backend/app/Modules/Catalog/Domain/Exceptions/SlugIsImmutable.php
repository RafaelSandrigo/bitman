<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class SlugIsImmutable extends BusinessRuleViolation
{
    public function __construct()
    {
        parent::__construct('O slug não pode mudar depois que o jogo foi publicado.');
    }
}
