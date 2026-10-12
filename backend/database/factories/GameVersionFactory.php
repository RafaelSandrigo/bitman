<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Versão em `import_requested`, para testes que não passam pela submissão. Os estados seguintes
 * existem para montar cenários; o caminho real é sempre pelos métodos do agregado.
 *
 * @extends Factory<GameVersion>
 */
final class GameVersionFactory extends Factory
{
    protected $model = GameVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'number' => 1,
            'status' => VersionStatus::ImportRequested,
            'repository_url' => fn (array $attributes): string => (string) Game::query()->whereKey($attributes['game_id'])->value('repository_url'),
            'requested_ref' => 'main',
            'requested_by_id' => User::factory()->developer(),
        ];
    }

    public function fetching(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VersionStatus::Fetching,
            'import_decided_by_id' => User::factory()->admin(),
            'import_decided_at' => now(),
        ]);
    }

    /** Já buscada, sem violações. */
    public function awaitingCommitReview(): static
    {
        return $this->fetching()->state(fn (array $attributes) => [
            'status' => VersionStatus::AwaitingCommitReview,
            'commit_sha' => fake()->sha1(),
            'validation_report' => ['blocked' => false, 'violations' => [], 'ignored' => [], 'ignored_total' => 0],
            'fetched_at' => now(),
        ]);
    }

    /** Já buscada, com violação bloqueante: terminal, o dono reenvia. */
    public function validationFailed(): static
    {
        return $this->awaitingCommitReview()->state(fn (array $attributes) => [
            'status' => VersionStatus::ValidationFailed,
            'validation_report' => [
                'blocked' => true,
                'violations' => [['code' => 'required_file_missing', 'message' => 'O arquivo obrigatório game.js não existe na raiz do repositório.', 'path' => 'game.js']],
                'ignored' => [],
                'ignored_total' => 0,
            ],
        ]);
    }

    /** Commit aprovado no Portão 1. Os arquivos o teste cria (VersionFile::record). */
    public function awaitingFileReview(): static
    {
        return $this->awaitingCommitReview()->state(fn (array $attributes) => [
            'status' => VersionStatus::AwaitingFileReview,
            'commit_decided_by_id' => User::factory()->admin(),
            'commit_decided_at' => now(),
        ]);
    }

    public function published(): static
    {
        return $this->awaitingFileReview()->state(fn (array $attributes) => [
            'status' => VersionStatus::Published,
            'public_id' => fake()->uuid(),
            'published_by_id' => User::factory()->admin(),
            'published_at' => now(),
        ]);
    }

    public function fetchFailed(): static
    {
        return $this->fetching()->state(fn (array $attributes) => [
            'status' => VersionStatus::FetchFailed,
            'fetch_failure' => 'ref_not_found',
            'fetched_at' => now(),
        ]);
    }
}
