<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Versioning\Domain\GameVersion;
use Database\Seeders\E2eSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Artifacts;

/* M11: o seeder da stack E2E nunca roda fora dela e publica o jogo-fixture pelo caminho real. */

it('recusa rodar fora de APP_ENV=e2e', function (): void {
    expect(fn () => $this->seed(E2eSeeder::class))->toThrow(RuntimeException::class, 'O E2eSeeder só roda com APP_ENV=e2e');

    expect(Game::count())->toBe(0);
});

it('na stack E2E, publica o jogo-fixture com os arquivos servidos', function (): void {
    Storage::fake('quarantine');
    $root = Artifacts::temporaryRoot();
    app()->detectEnvironment(fn (): string => 'e2e');

    try {
        $this->seed(E2eSeeder::class);

        $game = Game::query()->where('slug', 'e2e')->sole();
        $publicId = GameVersion::query()->whereKey($game->published_version_id)->value('public_id');
        expect($game->status)->toBe(GameStatus::Published)
            ->and(file_get_contents("{$root}/served/{$publicId}/game.js"))->toContain("type: 'game-over', score: 42");
    } finally {
        Artifacts::remove($root);
    }
});
