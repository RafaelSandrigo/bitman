<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;

it('o dono vê só os próprios jogos, do mais recente ao mais antigo, com o status', function (): void {
    $developer = User::factory()->developer()->create();
    Game::factory()->for($developer, 'owner')->create(['name' => 'Antigo', 'created_at' => now()->subDay()]);
    Game::factory()->for($developer, 'owner')->published()->create(['name' => 'Novo']);
    Game::factory()->create(['name' => 'De outra pessoa']);

    $this->actingAs($developer)->getJson('/api/v1/me/games')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Novo')
        ->assertJsonPath('data.0.status', 'published')
        ->assertJsonPath('data.1.name', 'Antigo')
        ->assertJsonPath('data.1.status', 'submitted')
        ->assertJsonMissingPath('data.0.owner');
});

it('visitante recebe 401', function (): void {
    $this->getJson('/api/v1/me/games')->assertUnauthorized();
});
