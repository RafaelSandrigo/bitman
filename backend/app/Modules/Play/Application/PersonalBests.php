<?php

declare(strict_types=1);

namespace App\Modules\Play\Application;

use App\Modules\Play\Domain\Score;

/** Melhor score visível do jogador num jogo: base do "novo recorde pessoal". */
final readonly class PersonalBests
{
    public function of(string $gameId, string $userId): ?int
    {
        $best = Score::query()->where('game_id', $gameId)->where('user_id', $userId)->whereNull('hidden_at')->max('value');

        return $best === null ? null : (int) $best;
    }
}
