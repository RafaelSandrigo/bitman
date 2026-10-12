<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Rules;

use App\Modules\Ingestion\Domain\PolicyLimits;
use App\Modules\Ingestion\Domain\RepositoryRule;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\Violation;

/** Cada obrigatório tem no máximo `max_file_bytes`. Tamanho nulo = o blob passou do limite do fetch. */
final readonly class RequiredFilesWithinSize implements RepositoryRule
{
    public function __construct(private PolicyLimits $limits) {}

    public function check(RepositorySnapshot $snapshot): array
    {
        $violations = [];
        foreach ($this->limits->requiredPaths as $path) {
            $entry = $snapshot->entry($path);
            if ($entry === null || ! $entry->isRegularFile()) {
                continue;
            }
            if ($entry->size === null || $entry->size > $this->limits->maxFileBytes) {
                $limit = number_format($this->limits->maxFileBytes / 1024, 0, ',', '.');
                $violations[] = Violation::at('file_too_large', "{$path} passa do limite de {$limit} KiB.", $entry);
            }
        }

        return $violations;
    }
}
