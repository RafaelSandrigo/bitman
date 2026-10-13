<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

use App\Modules\Play\Domain\Exceptions\InvalidScore;

/**
 * Score enviado pelo jogo: inteiro em `0..MAX`, nunca arredondado nem truncado (S2).
 * MAX é o maior inteiro exato em todas as camadas (docs/dominio.md#score-limite-técnico).
 */
final readonly class ScoreValue
{
    /** Number.MAX_SAFE_INTEGER (2^53 − 1). */
    public const MAX = 9_007_199_254_740_991;

    private function __construct(public int $value) {}

    public static function fromInt(int $value): self
    {
        if ($value < 0 || $value > self::MAX) {
            throw new InvalidScore;
        }

        return new self($value);
    }
}
