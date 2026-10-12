<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Rules;

use App\Modules\Ingestion\Domain\PolicyLimits;
use App\Modules\Ingestion\Domain\RepositoryRule;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\Violation;

/** `index.html`, `style.css` e `game.js` existem na raiz. */
final readonly class RequiredFilesPresent implements RepositoryRule
{
    public function __construct(private PolicyLimits $limits) {}

    public function check(RepositorySnapshot $snapshot): array
    {
        $violations = [];
        foreach ($this->limits->requiredPaths as $path) {
            if ($snapshot->entry($path) === null) {
                $violations[] = new Violation('required_file_missing', "O arquivo obrigatório {$path} não existe na raiz do repositório.", $path);
            }
        }

        return $violations;
    }
}
