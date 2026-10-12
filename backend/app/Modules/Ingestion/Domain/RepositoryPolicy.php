<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

use App\Modules\Ingestion\Domain\Rules\NoSubmodules;
use App\Modules\Ingestion\Domain\Rules\NoSymlinks;
use App\Modules\Ingestion\Domain\Rules\RequiredFilesAreRegular;
use App\Modules\Ingestion\Domain\Rules\RequiredFilesAreText;
use App\Modules\Ingestion\Domain\Rules\RequiredFilesPresent;
use App\Modules\Ingestion\Domain\Rules\RequiredFilesWithinSize;
use App\Modules\Ingestion\Domain\Rules\SafePaths;
use App\Modules\Ingestion\Domain\Rules\TreeWithinEntryLimit;

/**
 * Política de repositório do MVP (docs/dominio.md#política-de-repositório-mvp): aplica todas as
 * regras ao snapshot e lista o que fica de fora da allowlist.
 */
final readonly class RepositoryPolicy
{
    /** @param  list<RepositoryRule>  $rules */
    public function __construct(
        public PolicyLimits $limits,
        private array $rules,
    ) {}

    public static function standard(PolicyLimits $limits): self
    {
        return new self($limits, [
            new RequiredFilesPresent($limits),
            new RequiredFilesAreRegular($limits),
            new RequiredFilesWithinSize($limits),
            new RequiredFilesAreText($limits),
            new NoSubmodules,
            new NoSymlinks,
            new TreeWithinEntryLimit($limits),
            new SafePaths,
        ]);
    }

    public function evaluate(RepositorySnapshot $snapshot): ValidationReport
    {
        $violations = [];
        foreach ($this->rules as $rule) {
            array_push($violations, ...$rule->check($snapshot));
        }

        $ignored = [];
        $ignoredTotal = 0;
        foreach ($snapshot->entries as $entry) {
            if (in_array($entry->path, $this->limits->requiredPaths, true)) {
                continue;
            }
            $ignoredTotal++;
            if (count($ignored) < $this->limits->maxTreeEntries) {
                $ignored[] = new IgnoredFile($entry->displayPath(), $entry->mode, $entry->size);
            }
        }

        return new ValidationReport($violations, $ignored, $ignoredTotal);
    }
}
