<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use RuntimeException;

/**
 * I6, P1: o conteúdo lido (quarentena ou arquivo permanente) não tem o hash aprovado. Nada é
 * publicado nem servido; o erro é reportado.
 */
final class PublishedContentMismatch extends RuntimeException
{
    public function __construct(string $path)
    {
        parent::__construct("O conteúdo de {$path} não confere com o hash aprovado. Nada foi publicado.");
    }
}
