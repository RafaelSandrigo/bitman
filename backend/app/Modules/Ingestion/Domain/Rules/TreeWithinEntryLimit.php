<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Rules;

use App\Modules\Ingestion\Domain\PolicyLimits;
use App\Modules\Ingestion\Domain\RepositoryRule;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\Violation;

/** Total de entradas da árvore ≤ `max_tree_entries` (G7). */
final readonly class TreeWithinEntryLimit implements RepositoryRule
{
    public function __construct(private PolicyLimits $limits) {}

    public function check(RepositorySnapshot $snapshot): array
    {
        $count = count($snapshot->entries);

        return $count > $this->limits->maxTreeEntries
            ? [new Violation('too_many_entries', "O repositório tem {$count} arquivos; o limite é {$this->limits->maxTreeEntries}.")]
            : [];
    }
}
