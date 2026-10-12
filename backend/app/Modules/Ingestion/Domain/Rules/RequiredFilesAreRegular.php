<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Rules;

use App\Modules\Ingestion\Domain\PolicyLimits;
use App\Modules\Ingestion\Domain\RepositoryRule;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\Violation;

/** Os obrigatórios são blobs comuns (100644): nem symlink, nem executável, nem submodule (G5). */
final readonly class RequiredFilesAreRegular implements RepositoryRule
{
    public function __construct(private PolicyLimits $limits) {}

    public function check(RepositorySnapshot $snapshot): array
    {
        $violations = [];
        foreach ($this->limits->requiredPaths as $path) {
            $entry = $snapshot->entry($path);
            if ($entry !== null && ! $entry->isRegularFile()) {
                $violations[] = Violation::at('required_file_not_regular', "{$path} precisa ser um arquivo comum (modo 100644), mas é {$entry->kind()}.", $entry);
            }
        }

        return $violations;
    }
}
