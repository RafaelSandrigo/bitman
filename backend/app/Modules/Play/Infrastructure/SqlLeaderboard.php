<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure;

use App\Modules\Play\Domain\Leaderboard;
use App\Modules\Play\Domain\LeaderboardEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Ranking direto no PostgreSQL: o melhor score visível de cada jogador (`DISTINCT ON`), com a
 * versão e a data em que foi feito; `RANK()` dá a mesma posição aos empates. Só `MAX`/ordenação,
 * nunca soma: sem risco de overflow (docs/dominio.md#score-limite-técnico).
 */
final readonly class SqlLeaderboard implements Leaderboard
{
    public function __construct(private ConnectionInterface $db) {}

    public function top(string $gameId): array
    {
        /** @var list<object{user_id: string, value: int, created_at: string, name: string, number: int, position: int}> $rows */
        $rows = $this->db->select(<<<'SQL'
            WITH best AS (
                SELECT DISTINCT ON (s.user_id) s.user_id, s.value, s.created_at, s.game_version_id
                FROM scores s
                WHERE s.game_id = ? AND s.hidden_at IS NULL
                ORDER BY s.user_id, s.value DESC, s.created_at ASC
            )
            SELECT b.user_id, b.value, b.created_at, u.name, v.number,
                   RANK() OVER (ORDER BY b.value DESC) AS position
            FROM best b
            JOIN users u ON u.id = b.user_id
            JOIN game_versions v ON v.id = b.game_version_id
            ORDER BY b.value DESC, b.created_at ASC, b.user_id
            LIMIT ?
            SQL, [$gameId, self::SIZE]);

        return array_map(fn (object $row): LeaderboardEntry => new LeaderboardEntry(
            position: (int) $row->position,
            userId: (string) $row->user_id,
            playerName: (string) $row->name,
            value: (int) $row->value,
            versionNumber: (int) $row->number,
            achievedAt: CarbonImmutable::parse((string) $row->created_at),
        ), $rows);
    }

    public function positionOf(string $gameId, string $userId): ?int
    {
        $row = $this->db->selectOne(<<<'SQL'
            WITH best AS (
                SELECT user_id, MAX(value) AS value FROM scores
                WHERE game_id = ? AND hidden_at IS NULL
                GROUP BY user_id
            ), mine AS (
                SELECT value FROM best WHERE user_id = ?
            )
            SELECT CASE WHEN EXISTS (SELECT 1 FROM mine)
                THEN 1 + (SELECT COUNT(*) FROM best WHERE value > (SELECT value FROM mine))
            END AS position
            SQL, [$gameId, $userId]);

        return $row === null || $row->position === null ? null : (int) $row->position;
    }
}
