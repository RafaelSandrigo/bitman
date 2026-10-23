<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\GameSession;
use App\Modules\Play\Domain\GuestPlay;
use App\Modules\Play\Domain\Score;
use App\Modules\Play\Domain\SessionStatus;
use Tests\Support\Play;

/*
| Partida de visitante (docs/dominio.md#guestplay): joga sem conta, o score fica pendente e só
| entra no ranking quando uma conta com e-mail confirmado o salva (A5, I10, S1).
*/

beforeEach(function (): void {
    [$this->game, $this->version] = Play::playableGame(Game::factory()->published()->create(['slug' => 'snake']));
    $this->player = User::factory()->create(['name' => 'Ana']);
});

function startGuestPlay(string $slug = 'snake'): array
{
    return test()->postJson("/api/v1/games/{$slug}/guest-plays")->assertCreated()->json('data');
}

function sendGuestScore(array $play, mixed $score, ?string $token = null)
{
    return test()->postJson("/api/v1/guest-plays/{$play['id']}/score", ['score' => $score, 'token' => $token ?? $play['token']]);
}

function claimGuestScore(User $player, array $play, ?string $token = null)
{
    return test()->actingAs($player)->postJson("/api/v1/guest-plays/{$play['id']}/claim", ['token' => $token ?? $play['token']]);
}

/** Partida de visitante com score de 120 enviado depois de 30 segundos. */
function scoredGuestPlay(int $score = 120): array
{
    $play = startGuestPlay();
    test()->travel(30)->seconds();
    sendGuestScore($play, $score)->assertCreated();

    return $play;
}

it('visitante inicia uma partida sem conta: token de uso único, URL do jogo e prazo', function (): void {
    $response = $this->postJson('/api/v1/games/snake/guest-plays')->assertCreated();
    $data = $response->json('data');
    $play = GuestPlay::query()->findOrFail($data['id']);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($data['token'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($data['play_url'])->toBe(config('publication.games_origin')."/{$this->version->public_id}/index.html")
        ->and($data['min_seconds'])->toBe(5)
        ->and($play->token_hash)->toBe(hash('sha256', $data['token']))
        ->and($play->game_version_id)->toBe($this->version->id)
        ->and($play->value)->toBeNull();
});

it('I9: só há partida de visitante em jogo jogável', function (): void {
    Game::factory()->blocked()->create(['slug' => 'bloqueado']);

    $this->postJson('/api/v1/games/bloqueado/guest-plays')
        ->assertUnprocessable()
        ->assertJson(['message' => 'Este jogo não está disponível para partidas no momento.']);
});

it('o score do visitante fica pendente: não entra no ranking nem cria Score', function (): void {
    $play = startGuestPlay();
    $this->travel(30)->seconds();

    $response = sendGuestScore($play, 120)->assertCreated();

    expect($response->json('data.value'))->toBe(120)
        ->and($response->json('data.claim_expires_at'))->not->toBeNull()
        ->and(GuestPlay::query()->findOrFail($play['id'])->value)->toBe(120)
        ->and(Score::count())->toBe(0);
    $this->getJson('/api/v1/games/snake/leaderboard')->assertJson(['data' => []]);
});

it('I10: o score do visitante segue as regras da partida (token, duração mínima e uso único)', function (): void {
    $play = startGuestPlay();

    sendGuestScore($play, 10)->assertUnprocessable()->assertJson(['message' => 'A partida durou menos que o mínimo para registrar o score.']);
    $this->travel(30)->seconds();
    sendGuestScore($play, 10, str_repeat('0', 64))->assertUnprocessable()->assertJson(['message' => 'Sessão de jogo inválida.']);
    sendGuestScore($play, 10)->assertCreated();
    sendGuestScore($play, 999)->assertConflict();

    expect(GuestPlay::query()->findOrFail($play['id'])->value)->toBe(10);
});

it('S2: o score do visitante é um inteiro na faixa, nunca convertido', function (mixed $score): void {
    $play = startGuestPlay();
    $this->travel(30)->seconds();

    sendGuestScore($play, $score)->assertUnprocessable()->assertJsonValidationErrors('score');
})->with(['texto' => '10', 'fração' => 1.5, 'negativo' => -1]);

it('o prazo da partida de visitante também vale', function (): void {
    $play = startGuestPlay();
    $this->travel(3)->hours();

    sendGuestScore($play, 10)->assertUnprocessable()->assertJson(['message' => 'O prazo desta partida acabou. Inicie outra para jogar de novo.']);
});

it('ao entrar, a conta confirmada salva o score pendente: vira Score no ranking com a posição', function (): void {
    Play::score(User::factory()->create(), $this->version, 500);
    $play = scoredGuestPlay(120);

    claimGuestScore($this->player, $play)
        ->assertCreated()
        ->assertJson(['data' => [
            'value' => 120,
            'position' => 2,
            'personal_best' => true,
            'previous_best' => null,
            'game' => ['slug' => 'snake', 'name' => $this->game->name],
        ]]);

    $score = Score::query()->where('user_id', $this->player->id)->sole();
    $session = GameSession::query()->findOrFail($score->game_session_id);
    $guest = GuestPlay::query()->findOrFail($play['id']);
    expect($score->value)->toBe(120)
        ->and($score->game_version_id)->toBe($this->version->id)
        ->and($session->status)->toBe(SessionStatus::Scored)
        ->and($session->user_id)->toBe($this->player->id)
        ->and($session->started_at->equalTo($guest->started_at))->toBeTrue()
        ->and($guest->claimed_score_id)->toBe($score->id);
});

it('A5: salvar exige sessão e e-mail confirmado', function (): void {
    $play = scoredGuestPlay();

    $this->postJson("/api/v1/guest-plays/{$play['id']}/claim", ['token' => $play['token']])->assertUnauthorized();
    claimGuestScore(User::factory()->unverified()->create(), $play)->assertForbidden();

    expect(Score::count())->toBe(0);
});

it('S1: o score pendente é salvo uma única vez, por uma única conta', function (): void {
    $play = scoredGuestPlay();

    claimGuestScore($this->player, $play)->assertCreated();
    claimGuestScore($this->player, $play)->assertConflict()->assertJson(['message' => 'Este score já foi salvo.']);
    claimGuestScore(User::factory()->create(), $play)->assertConflict();

    expect(Score::count())->toBe(1);
});

it('não salva sem o token certo, nem partida sem score enviado', function (): void {
    $play = startGuestPlay();

    claimGuestScore($this->player, $play)->assertUnprocessable()->assertJson(['message' => 'Esta partida não tem score para salvar.']);
    $this->travel(30)->seconds();
    sendGuestScore($play, 10)->assertCreated();
    claimGuestScore($this->player, $play, str_repeat('0', 64))->assertUnprocessable()->assertJson(['message' => 'Sessão de jogo inválida.']);

    expect(Score::count())->toBe(0);
});

it('o score pendente vence: depois do prazo não pode mais ser salvo', function (): void {
    $play = scoredGuestPlay();
    $this->travel(25)->hours();

    claimGuestScore($this->player, $play)
        ->assertUnprocessable()
        ->assertJson(['message' => 'O prazo para salvar este score acabou. Jogue de novo para entrar no ranking.']);
});

it('jogo bloqueado depois da partida: o score pendente não entra', function (): void {
    $play = scoredGuestPlay();
    Game::query()->whereKey($this->game->id)->update(['status' => 'blocked', 'blocked_by_id' => User::factory()->admin()->create()->id, 'block_reason' => 'x', 'blocked_at' => now()]);

    claimGuestScore($this->player, $play)
        ->assertUnprocessable()
        ->assertJson(['message' => 'Este jogo não está disponível para partidas no momento.']);
    expect(Score::count())->toBe(0);
});

it('S2: limite de partidas de visitante por IP', function (): void {
    foreach (range(1, 30) as $ignored) {
        $this->postJson('/api/v1/games/snake/guest-plays')->assertCreated();
    }

    $this->postJson('/api/v1/games/snake/guest-plays')->assertTooManyRequests();
});
