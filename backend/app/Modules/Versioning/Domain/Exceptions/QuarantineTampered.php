<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use RuntimeException;

/** P1: o blob na quarentena não confere com o hash do fetch. Nunca é mostrado nem aprovado. */
final class QuarantineTampered extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O conteúdo na quarentena não confere com o hash registrado no fetch.');
    }
}
