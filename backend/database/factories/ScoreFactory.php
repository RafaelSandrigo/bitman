<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Play\Domain\GameSession;
use App\Modules\Play\Domain\Score;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Score de uma sessão já pontuada; jogador, jogo e versão vêm da sessão. Para montar rankings,
 * passe `game_session_id` de uma sessão do jogo desejado.
 *
 * @extends Factory<Score>
 */
final class ScoreFactory extends Factory
{
    protected $model = Score::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory()->scored(),
            'user_id' => fn (array $a): string => $this->session($a)->user_id,
            'game_id' => fn (array $a): string => $this->session($a)->game_id,
            'game_version_id' => fn (array $a): string => $this->session($a)->game_version_id,
            'value' => fake()->numberBetween(0, 10_000),
            'created_at' => now(),
        ];
    }

    public function flagged(string $reason = 'Fora do padrão em teste.'): static
    {
        return $this->state(fn (array $attributes) => ['flagged' => true, 'flag_reason' => $reason]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function session(array $attributes): GameSession
    {
        return GameSession::query()->whereKey($attributes['game_session_id'])->firstOrFail();
    }
}
