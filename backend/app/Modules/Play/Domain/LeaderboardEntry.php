<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

use Carbon\CarbonImmutable;

/** Uma linha do ranking: o melhor score visível do jogador no jogo, com a versão em que foi feito. */
final readonly class LeaderboardEntry
{
    public function __construct(
        public int $position,
        public string $userId,
        public string $playerName,
        public int $value,
        public int $versionNumber,
        public CarbonImmutable $achievedAt,
    ) {}
}
