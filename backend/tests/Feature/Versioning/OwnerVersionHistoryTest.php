<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;

/* M2: o dono vê todas as versões do próprio jogo, com o estado e o motivo de cada uma. */

beforeEach(function (): void {
    $this->owner = User::factory()->developer()->create();
    $this->game = Game::factory()->for($this->owner, 'owner')->create(['name' => 'Snake']);
});

it('lista todas as versões do jogo, da mais recente para a mais antiga', function (): void {
    GameVersion::factory()->for($this->game)->fetchFailed()->create(['number' => 1]);
    GameVersion::factory()->for($this->game)->published()->create(['number' => 2]);
    GameVersion::factory()->for($this->game)->create(['number' => 3, 'import_rejection_reason' => null]);

    $this->actingAs($this->owner)->getJson("/api/v1/me/games/{$this->game->id}/versions")
        ->assertOk()
        ->assertJsonPath('meta.game', ['id' => $this->game->id, 'name' => 'Snake', 'slug' => $this->game->slug])
        ->assertJsonPath('data.*.number', [3, 2, 1])
        ->assertJsonPath('data.2.fetch_failure.code', 'ref_not_found')
        ->assertJsonPath('data.1.status', 'published');
});

it('jogo de outra pessoa ou inexistente responde 404 (sem revelar que existe)', function (): void {
    $other = User::factory()->developer()->create();

    $this->actingAs($other)->getJson("/api/v1/me/games/{$this->game->id}/versions")->assertNotFound();
    $this->actingAs($this->owner)->getJson('/api/v1/me/games/01jzzzzzzzzzzzzzzzzzzzzzzz/versions')->assertNotFound();
});

it('exige sessão e e-mail confirmado', function (): void {
    $this->getJson("/api/v1/me/games/{$this->game->id}/versions")->assertUnauthorized();
});
