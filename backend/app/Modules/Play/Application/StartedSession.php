<?php

declare(strict_types=1);

namespace App\Modules\Play\Application;

use App\Modules\Play\Domain\GameSession;

/** A partida criada. O token em claro só existe aqui: vai uma vez para a SPA e nunca é guardado. */
final readonly class StartedSession
{
    public function __construct(
        public GameSession $session,
        public string $token,
        /** {origem de jogos}/{publicId}/index.html da versão desta sessão. */
        public string $playUrl,
        public int $minSessionSeconds,
    ) {}
}
