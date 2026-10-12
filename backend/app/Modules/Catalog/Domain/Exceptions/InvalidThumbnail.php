<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Shared\Domain\BusinessRuleViolation;

final class InvalidThumbnail extends BusinessRuleViolation
{
    public static function unsupported(): self
    {
        return new self('A thumbnail deve ser uma imagem PNG, JPEG ou WebP válida.');
    }

    public static function tooLarge(int $maxBytes): self
    {
        return new self('A thumbnail deve ter no máximo '.intdiv($maxBytes, 1024 * 1024).' MB.');
    }

    public static function badDimensions(int $min, int $max): self
    {
        return new self("A thumbnail deve ter entre {$min} e {$max} pixels de largura e de altura.");
    }
}
