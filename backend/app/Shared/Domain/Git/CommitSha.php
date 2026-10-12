<?php

declare(strict_types=1);

namespace App\Shared\Domain\Git;

/** SHA de commit resolvido no fetch (SHA-1 ou SHA-256, em minúsculas). Vincula o que foi analisado (I13). */
final readonly class CommitSha
{
    private function __construct(public string $value) {}

    public static function fromString(string $sha): self
    {
        if (preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/D', $sha) !== 1) {
            throw new InvalidCommitSha;
        }

        return new self($sha);
    }

    public function short(): string
    {
        return substr($this->value, 0, 7);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
