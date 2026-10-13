<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use Tests\Support\Play;

/*
| Histórico de partidas do jogador.
*/

it('lista as partidas do jogador, da mais recente para a mais antiga, sem as de outros', function (): void {
    [$game, $version] = Play::playableGame();
    $player = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $this->travel(-1)->hours();
    Play::score($player, $version, 10);
    $this->travelBack();
    Play::score($player, $version, 20, ['flagged' => true, 'flag_reason' => 'x', 'hidden_by_id' => $admin->id, 'hidden_at' => now(), 'hide_reason' => 'Forjado.']);
    Play::score(User::factory()->create(), $version, 30);

    $response = $this->actingAs($player)->getJson('/api/v1/me/scores')->assertOk();

    expect(array_column($response->json('data'), 'value'))->toBe([20, 10])
        ->and($response->json('data.0'))->toMatchArray(['game' => ['slug' => $game->slug, 'name' => $game->name], 'version_number' => 1, 'hidden' => true])
        // A sinalização e o motivo da ocultação são da moderação.
        ->and(array_keys($response->json('data.0')))->toBe(['id', 'game', 'version_number', 'value', 'hidden', 'created_at']);
});

it('exige login e e-mail confirmado', function (): void {
    $this->getJson('/api/v1/me/scores')->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())->getJson('/api/v1/me/scores')->assertForbidden();
});
