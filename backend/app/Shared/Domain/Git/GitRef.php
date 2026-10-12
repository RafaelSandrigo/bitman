<?php

declare(strict_types=1);

namespace App\Shared\Domain\Git;

/**
 * Branch, tag ou SHA a importar (G1). O conjunto de caracteres não permite opções do git,
 * expressões de revisão (`@{`, `~`, `^`, `:`) nem caracteres de shell; as demais regras seguem
 * o `git check-ref-format`.
 */
final readonly class GitRef
{
    private function __construct(public string $value) {}

    public static function fromString(string $ref): self
    {
        $valid = preg_match('~^[A-Za-z0-9._/-]{1,100}$~D', $ref) === 1
            && ! str_starts_with($ref, '-')
            && ! str_starts_with($ref, '/')
            && ! str_ends_with($ref, '/')
            && ! str_ends_with($ref, '.')
            && ! str_ends_with($ref, '.lock')
            && ! str_contains($ref, '..')
            && ! str_contains($ref, '//')
            && ! str_starts_with($ref, '.')
            && ! str_contains($ref, '/.');

        if (! $valid) {
            throw new InvalidGitRef;
        }

        return new self($ref);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
