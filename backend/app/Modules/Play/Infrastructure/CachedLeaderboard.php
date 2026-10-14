<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure;

use App\Modules\Play\Domain\Leaderboard;
use App\Modules\Play\Domain\LeaderboardEntry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;

/**
 * Ranking em cache (Redis em produção), invalidado a cada score registrado, ocultado ou devolvido
 * (depois do commit). O TTL limita o caso raro de uma leitura concorrente regravar um ranking antigo.
 * A posição de um jogador é sempre lida do banco. O cache guarda só arrays: a desserialização de
 * objetos está desligada (`cache.serializable_classes`).
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
        /** @var list<array{position: int, user_id: string, player_name: string, value: int, version_number: int, achieved_at: string}> $rows */
        $rows = $this->cache->remember(self::key($gameId), $this->ttlSeconds, fn (): array => array_map(
            static fn (LeaderboardEntry $entry): array => [
                'position' => $entry->position,
                'user_id' => $entry->userId,
                'player_name' => $entry->playerName,
                'value' => $entry->value,
                'version_number' => $entry->versionNumber,
                'achieved_at' => $entry->achievedAt->toIso8601ZuluString('microsecond'),
            ],
            $this->inner->top($gameId),
        ));

        return array_map(static fn (array $row): LeaderboardEntry => new LeaderboardEntry(
            $row['position'],
            $row['user_id'],
            $row['player_name'],
            $row['value'],
            $row['version_number'],
            CarbonImmutable::parse($row['achieved_at']),
        ), $rows);
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
