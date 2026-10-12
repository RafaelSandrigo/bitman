<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain\Rules;

use App\Modules\Ingestion\Domain\RepositoryRule;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\Violation;

/**
 * Nenhum path com `..`, `.`, `.git`, barra inicial, `\`, caractere de controle, segmento vazio ou
 * UTF-8 inválido (G6). Vale para a árvore inteira: nomes hostis indicam um repositório hostil.
 */
final readonly class SafePaths implements RepositoryRule
{
    public function check(RepositorySnapshot $snapshot): array
    {
        $violations = [];
        foreach ($snapshot->entries as $entry) {
            if (! self::isSafe($entry->path)) {
                $violations[] = Violation::at('unsafe_path', "Nome de arquivo não permitido: {$entry->displayPath()}.", $entry);
            }
        }

        return $violations;
    }

    public static function isSafe(string $path): bool
    {
        if (str_starts_with($path, '/') || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1 || preg_match('//u', $path) !== 1) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if (in_array($segment, ['', '.', '..'], true) || strtolower($segment) === '.git') {
                return false;
            }
        }

        return true;
    }
}
