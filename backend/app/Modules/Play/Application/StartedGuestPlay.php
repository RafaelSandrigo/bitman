<?php

declare(strict_types=1);

namespace App\Modules\Play\Application;

use App\Modules\Play\Domain\GuestPlay;

/** A partida de visitante criada. O token em claro só existe aqui e nunca é guardado. */
final readonly class StartedGuestPlay
{
    public function __construct(
        public GuestPlay $play,
        public string $token,
        public string $playUrl,
        public int $minSessionSeconds,
    ) {}
}
