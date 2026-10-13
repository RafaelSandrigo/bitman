<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Leaderboard;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Play;

/*
| Ranking por jogo: melhor score por jogador, em todas as versões, sem os ocultos; cache invalidado
| a cada novo score.
*/

beforeEach(function (): void {
    [$this->game, $this->version] = Play::playableGame(Game::factory()->published()->create(['slug' => 'snake', 'name' => 'Snake']));
});

it('mostra o melhor score de cada jogador, do maior para o menor, com a versão', function (): void {
    $ana = User::factory()->create(['name' => 'Ana']);
    $bia = User::factory()->create(['name' => 'Bia']);
    GameVersion::query()->whereKey($this->version->id)->update(['status' => VersionStatus::Superseded]);
    [, $v2] = Play::playableGame($this->game, 2);
    Play::score($ana, $this->version, 300);
    Play::score($ana, $v2, 100);
    Play::score($bia, $v2, 200);

    $this->getJson('/api/v1/games/snake/leaderboard')
        ->assertOk()
        ->assertJsonPath('meta.game', ['slug' => 'snake', 'name' => 'Snake'])
        ->assertJsonPath('data.0', [
            'position' => 1, 'player_name' => 'Ana', 'value' => 300, 'version_number' => 1,
            'achieved_at' => $this->json('GET', '/api/v1/games/snake/leaderboard')->json('data.0.achieved_at'), 'is_me' => false,
        ])
        ->assertJsonPath('data.1.player_name', 'Bia')
        ->assertJsonPath('data.1.version_number', 2)
        ->assertJsonCount(2, 'data');
});

it('empates dividem a posição; entre eles, quem chegou primeiro aparece antes', function (): void {
    $this->travel(-2)->hours();
    Play::score(User::factory()->create(['name' => 'Primeira']), $this->version, 50);
    $this->travelBack();
    Play::score(User::factory()->create(['name' => 'Segunda']), $this->version, 50);
    Play::score(User::factory()->create(['name' => 'Terceira']), $this->version, 10);

    $data = $this->getJson('/api/v1/games/snake/leaderboard')->json('data');

    expect(array_column($data, 'player_name'))->toBe(['Primeira', 'Segunda', 'Terceira'])
        ->and(array_column($data, 'position'))->toBe([1, 1, 3]);
});

it('scores ocultos não entram; sinalizados entram até o admin decidir', function (): void {
    $admin = User::factory()->admin()->create();
    Play::score(User::factory()->create(['name' => 'Oculta']), $this->version, 900, ['hidden_by_id' => $admin->id, 'hidden_at' => now(), 'hide_reason' => 'Forjado.']);
    Play::score(User::factory()->create(['name' => 'Sinalizada']), $this->version, 800, ['flagged' => true, 'flag_reason' => 'Fora do padrão.']);

    $data = $this->getJson('/api/v1/games/snake/leaderboard')->json('data');

    expect(array_column($data, 'player_name'))->toBe(['Sinalizada'])
        ->and($data[0])->not->toHaveKey('flagged');
});

it('marca a linha de quem está logado e não expõe ids nem e-mails', function (): void {
    $me = User::factory()->create(['name' => 'Eu']);
    Play::score($me, $this->version, 10);
    Play::score(User::factory()->create(), $this->version, 20);

    $data = $this->actingAs($me)->getJson('/api/v1/games/snake/leaderboard')->json('data');

    expect(array_column($data, 'is_me'))->toBe([false, true])
        ->and(array_keys($data[0]))->toBe(['position', 'player_name', 'value', 'version_number', 'achieved_at', 'is_me']);
});

it('limita às primeiras posições', function (): void {
    expect(Leaderboard::SIZE)->toBe(100);
});

it('ranking de jogo não jogável responde 404 (I9)', function (): void {
    Game::factory()->blocked()->create(['slug' => 'bloqueado']);

    $this->getJson('/api/v1/games/bloqueado/leaderboard')->assertNotFound();
    $this->getJson('/api/v1/games/nao-existe/leaderboard')->assertNotFound();
});

it('o ranking fica em cache e é invalidado a cada novo score', function (): void {
    config(['cache.default' => 'array']);
    Play::score(User::factory()->create(['name' => 'Ana']), $this->version, 10);
    expect($this->getJson('/api/v1/games/snake/leaderboard')->json('data'))->toHaveCount(1)
        ->and(Cache::has('play:leaderboard:'.strtolower($this->game->id)))->toBeTrue();

    // Gravado direto no banco (sem evento): o cache continua servindo o ranking anterior.
    Play::score(User::factory()->create(['name' => 'Bia']), $this->version, 20);
    expect($this->getJson('/api/v1/games/snake/leaderboard')->json('data'))->toHaveCount(1);

    // Pela API, o evento invalida o cache.
    $player = User::factory()->create(['name' => 'Caio']);
    $session = Play::openSession($player, $this->version);
    $this->actingAs($player)->postJson("/api/v1/game-sessions/{$session->id}/score", ['score' => 5, 'token' => Play::TOKEN])->assertCreated();

    expect(array_column($this->getJson('/api/v1/games/snake/leaderboard')->json('data'), 'player_name'))->toBe(['Bia', 'Ana', 'Caio']);
});
