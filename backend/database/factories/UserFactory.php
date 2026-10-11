<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Todo usuário criado pela factory tem a role player, como no cadastro real.
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::Player));
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function developer(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::Developer));
    }

    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::Admin));
    }
}
