<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure;

use App\Modules\Play\Domain\ScoreDistribution;
use App\Modules\Play\Domain\ScoreStatistics;
use Illuminate\Database\ConnectionInterface;

/** O padrão do jogo usa só os scores do ranking: um sinalizado em revisão não puxa o p99 para cima (M20). */
final readonly class SqlScoreDistribution implements ScoreDistribution
{
    public function __construct(private ConnectionInterface $db) {}

    public function of(string $gameId): ScoreStatistics
    {
        $row = $this->db->selectOne(<<<'SQL'
            SELECT COUNT(*) AS count,
                   percentile_disc(0.99) WITHIN GROUP (ORDER BY s.value) AS p99,
                   percentile_cont(0.99) WITHIN GROUP (ORDER BY s.value / GREATEST(EXTRACT(EPOCH FROM gs.ended_at - gs.started_at), 1)) AS p99_per_second
            FROM scores s
            JOIN game_sessions gs ON gs.id = s.game_session_id
            WHERE s.game_id = ? AND s.hidden_at IS NULL AND (NOT s.flagged OR s.flag_cleared_at IS NOT NULL)
            SQL, [$gameId]);

        return new ScoreStatistics(
            (int) ($row->count ?? 0),
            isset($row->p99) ? (int) $row->p99 : null,
            isset($row->p99_per_second) ? (float) $row->p99_per_second : null,
        );
    }
}
