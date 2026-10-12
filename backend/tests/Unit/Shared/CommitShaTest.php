<?php

declare(strict_types=1);

use App\Shared\Domain\Git\CommitSha;
use App\Shared\Domain\Git\InvalidCommitSha;

it('aceita SHA-1 e SHA-256 em minúsculas', function (string $sha): void {
    expect(CommitSha::fromString($sha)->value)->toBe($sha)
        ->and(CommitSha::fromString($sha)->short())->toBe(substr($sha, 0, 7));
})->with([str_repeat('a', 40), str_repeat('0', 64)]);

it('recusa qualquer outra coisa', function (string $sha): void {
    expect(fn () => CommitSha::fromString($sha))->toThrow(InvalidCommitSha::class);
})->with(['', 'main', str_repeat('A', 40), str_repeat('a', 39), str_repeat('a', 41), str_repeat('a', 40)."\n", 'HEAD^{commit}']);
