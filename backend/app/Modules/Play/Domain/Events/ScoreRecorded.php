<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain\Events;

/**
 * Um score entrou no ranking. Não é auditado (é ação do jogador, não decisão administrativa);
 * invalida o ranking em cache do jogo.
 */
final readonly class ScoreRecorded
{
    public function __construct(
        public string $scoreId,
        public string $gameId,
        public string $userId,
        public int $value,
        public bool $flagged,
    ) {}
}
