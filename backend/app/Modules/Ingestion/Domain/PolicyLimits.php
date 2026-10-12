<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

use InvalidArgumentException;

/** Parâmetros da política de repositório (config/ingestion.php). */
final readonly class PolicyLimits
{
    /**
     * @param  list<string>  $requiredPaths  arquivos da raiz que formam o jogo (os únicos extraídos)
     */
    public function __construct(
        public array $requiredPaths,
        public int $maxFileBytes,
        public int $maxTreeEntries,
    ) {
        if ($requiredPaths === [] || $maxFileBytes < 1 || $maxTreeEntries < 1) {
            throw new InvalidArgumentException('Limites da política de repositório inválidos.');
        }
        foreach ($requiredPaths as $path) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $path) !== 1) {
                throw new InvalidArgumentException("Path obrigatório inválido: {$path}");
            }
        }
    }
}
