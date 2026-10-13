<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\GameSession;
use App\Modules\Play\Domain\SessionStatus;
use App\Modules\Play\Domain\SessionToken;
use App\Modules\Versioning\Domain\GameVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sessão aberta numa versão publicada, iniciada há 10 minutos, com o token `TOKEN`. O caminho
 * real é sempre `GameSession::start()`.
 *
 * @extends Factory<GameSession>
 */
final class GameSessionFactory extends Factory
{
    public const TOKEN = 'token-de-teste';

    protected $model = GameSession::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_version_id' => GameVersion::factory()->published(),
            'game_id' => fn (array $attributes): string => (string) GameVersion::query()->whereKey($attributes['game_version_id'])->value('game_id'),
            'token_hash' => SessionToken::hashOf(self::TOKEN),
            'status' => SessionStatus::Open,
            'started_at' => now()->subMinutes(10),
            'expires_at' => now()->addHour(),
        ];
    }

    public function scored(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SessionStatus::Scored, 'ended_at' => now()]);
    }
}
