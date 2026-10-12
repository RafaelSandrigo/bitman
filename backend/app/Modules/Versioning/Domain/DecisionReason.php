<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

use App\Modules\Versioning\Domain\Exceptions\InvalidDecisionReason;

/** Motivo de uma rejeição: obrigatório (I11), registrado na auditoria. */
final readonly class DecisionReason
{
    public const MIN = 3;

    public const MAX = 1000;

    private function __construct(public string $value) {}

    public static function fromString(string $reason): self
    {
        $reason = trim($reason);
        $length = mb_strlen($reason);
        if ($length < self::MIN || $length > self::MAX) {
            throw new InvalidDecisionReason;
        }

        return new self($reason);
    }
}
