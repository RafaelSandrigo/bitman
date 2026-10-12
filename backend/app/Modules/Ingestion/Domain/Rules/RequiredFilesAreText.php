<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Rules;

use App\Modules\Ingestion\Domain\PolicyLimits;
use App\Modules\Ingestion\Domain\RepositoryRule;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\Violation;

/**
 * Os obrigatórios são texto UTF-8 válido, sem bytes nulos. Um obrigatório comum e dentro do
 * limite cujo conteúdo não veio no snapshot também bloqueia: não há o que revisar.
 */
final readonly class RequiredFilesAreText implements RepositoryRule
{
    public function __construct(private PolicyLimits $limits) {}

    public function check(RepositorySnapshot $snapshot): array
    {
        $violations = [];
        foreach ($this->limits->requiredPaths as $path) {
            $entry = $snapshot->entry($path);
            if ($entry === null || ! $entry->isRegularFile() || $entry->size === null || $entry->size > $this->limits->maxFileBytes) {
                continue;
            }

            $content = $snapshot->blob($path);
            if ($content === null) {
                $violations[] = Violation::at('content_missing', "O conteúdo de {$path} não foi entregue pelo fetch.", $entry);
            } elseif (str_contains($content, "\0") || preg_match('//u', $content) !== 1) {
                $violations[] = Violation::at('not_text', "{$path} não é texto UTF-8 válido (ou contém bytes nulos).", $entry);
            }
        }

        return $violations;
    }
}
