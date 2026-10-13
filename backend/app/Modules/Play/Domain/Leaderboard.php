<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

/**
 * Ranking por jogo: o melhor score de cada jogador, em todas as versões, sem os ocultos.
 * Empates ficam na mesma posição (1, 1, 3); entre eles, quem chegou primeiro aparece antes.
 */
interface Leaderboard
{
    public const SIZE = 100;

    /** @return list<LeaderboardEntry> as SIZE primeiras posições */
    public function top(string $gameId): array;

    /** Posição do jogador, ou null se ele não tem score visível no jogo. */
    public function positionOf(string $gameId, string $userId): ?int;
}
