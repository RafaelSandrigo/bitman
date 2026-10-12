<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Rules;

use App\Modules\Ingestion\Domain\RepositoryRule;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\Violation;

/** Nenhum symlink no repositório, nem fora dos obrigatórios (G5). */
final readonly class NoSymlinks implements RepositoryRule
{
    public function check(RepositorySnapshot $snapshot): array
    {
        $violations = [];
        foreach ($snapshot->entries as $entry) {
            if ($entry->isSymlink()) {
                $violations[] = Violation::at('symlink', "O repositório tem um symlink em {$entry->displayPath()}.", $entry);
            }
        }

        return $violations;
    }
}
