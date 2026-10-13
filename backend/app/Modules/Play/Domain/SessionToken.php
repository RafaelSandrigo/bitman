<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

/**
 * Token de uso único da sessão (I10, S1). Só o hash é guardado; o valor em claro vai uma única
 * vez para a SPA que iniciou a partida e nunca chega ao jogo.
 */
final readonly class SessionToken
{
    private function __construct(public string $plain, public string $hash) {}

    public static function issue(): self
    {
        $plain = bin2hex(random_bytes(32));

        return new self($plain, self::hashOf($plain));
    }

    public static function hashOf(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
