<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Jogo já submetido, para testes que não passam pela submissão (que é a única forma real
 * de criar um jogo: Game::submit()).
 *
 * @extends Factory<Game>
 */
final class GameFactory extends Factory
{
    protected $model = Game::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->numberBetween(1, 999);

        return [
            'slug' => str($name)->slug()->toString(),
            'name' => ucfirst($name),
            'description' => fake()->sentence(),
            'author_name' => fake()->name(),
            'thumbnail_path' => bin2hex(random_bytes(16)).'.webp',
            'owner_id' => User::factory()->developer(),
            'repository_url' => 'https://github.com/'.fake()->userName().'/'.str($name)->slug(),
            'default_ref' => 'main',
            'status' => GameStatus::Submitted,
        ];
    }

    /** Já teve uma versão publicada. A versão em si o teste cria (GameVersionFactory::published). */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => GameStatus::Published, 'first_published_at' => now()]);
    }

    public function blocked(): static
    {
        return $this->published()->state(fn (array $attributes) => [
            'status' => GameStatus::Blocked,
            'blocked_by_id' => User::factory()->admin(),
            'blocked_at' => now(),
            'block_reason' => 'Bloqueado em teste.',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => GameStatus::Rejected]);
    }
}
