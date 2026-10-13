<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure;

use App\Modules\Play\Domain\Leaderboard;
use Illuminate\Contracts\Cache\Repository;

/**
 * Ranking em cache (Redis em produção), invalidado a cada score registrado, ocultado ou devolvido
 * (depois do commit). O TTL limita o caso raro de uma leitura concorrente regravar um ranking antigo.
 * A posição de um jogador é sempre lida do banco.
 */
final readonly class CachedLeaderboard implements Leaderboard
{
    public function __construct(
        private Leaderboard $inner,
        private Repository $cache,
        private int $ttlSeconds,
    ) {}

    public function top(string $gameId): array
    {
        return $this->cache->remember(self::key($gameId), $this->ttlSeconds, fn (): array => $this->inner->top($gameId));
    }

    public function positionOf(string $gameId, string $userId): ?int
    {
        return $this->inner->positionOf($gameId, $userId);
    }

    public function forget(string $gameId): void
    {
        $this->cache->forget(self::key($gameId));
    }

    private static function key(string $gameId): string
    {
        return 'play:leaderboard:'.strtolower($gameId);
    }
}
