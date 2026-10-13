<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure;

use App\Modules\Play\Domain\ScoreDistribution;
use App\Modules\Play\Domain\ScoreStatistics;
use Illuminate\Database\ConnectionInterface;

final readonly class SqlScoreDistribution implements ScoreDistribution
{
    public function __construct(private ConnectionInterface $db) {}

    public function of(string $gameId): ScoreStatistics
    {
        $row = $this->db->selectOne(<<<'SQL'
            SELECT COUNT(*) AS count, percentile_disc(0.99) WITHIN GROUP (ORDER BY value) AS p99
            FROM scores
            WHERE game_id = ? AND hidden_at IS NULL
            SQL, [$gameId]);

        return new ScoreStatistics((int) ($row->count ?? 0), isset($row->p99) ? (int) $row->p99 : null);
    }
}
