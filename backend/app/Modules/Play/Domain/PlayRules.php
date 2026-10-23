<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

/** Limites das partidas (config/play.php). */
final readonly class PlayRules
{
    public function __construct(
        /** Prazo para enviar o score depois de iniciar a partida. */
        public int $sessionTtlSeconds,
        /** I10: uma partida mais curta que isso não registra score. */
        public int $minSessionSeconds,
        /** Prazo para uma conta salvar o score de uma partida de visitante. */
        public int $guestClaimTtlSeconds = 86_400,
    ) {}
}
