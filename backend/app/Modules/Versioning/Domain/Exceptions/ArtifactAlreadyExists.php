<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain\Exceptions;

use RuntimeException;

/** I7: o artefato é escrito uma única vez. Um destino que já existe nunca é sobrescrito. */
final class ArtifactAlreadyExists extends RuntimeException
{
    public function __construct(string $what)
    {
        parent::__construct("O artefato já existe e não pode ser sobrescrito: {$what}.");
    }
}
