<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Rules;

use App\Modules\Ingestion\Domain\RepositoryRule;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\Violation;

/** Nenhum submodule: nem `.gitmodules`, nem entradas 160000 (G5). */
final readonly class NoSubmodules implements RepositoryRule
{
    public function check(RepositorySnapshot $snapshot): array
    {
        $violations = [];
        foreach ($snapshot->entries as $entry) {
            if ($entry->isSubmodule()) {
                $violations[] = Violation::at('submodule', "O repositório tem um submodule em {$entry->displayPath()}.", $entry);
            } elseif ($entry->path === '.gitmodules') {
                $violations[] = Violation::at('submodule', 'O repositório tem um arquivo .gitmodules.', $entry);
            }
        }

        return $violations;
    }
}
