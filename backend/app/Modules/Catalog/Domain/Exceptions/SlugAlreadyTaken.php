<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Modules\Catalog\Domain\Slug;
use App\Shared\Domain\BusinessRuleViolation;

final class SlugAlreadyTaken extends BusinessRuleViolation
{
    public static function for(Slug $slug): self
    {
        return new self("O slug {$slug->value} já é usado por outro jogo.");
    }
}
