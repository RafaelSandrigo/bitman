<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\GameSession;
use App\Modules\Play\Domain\Score;
use App\Modules\Play\Domain\SessionStatus;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\Support\Play;

/*
| Partida e score pela API (I9, I10, S1, S2, A5).
*/

beforeEach(function (): void {
    [$this->game, $this->version] = Play::playableGame(Game::factory()->published()->create(['slug' => 'snake']));
    $this->player = User::factory()->create(['name' => 'Ana']);
});

function startSession(User $player, string $slug = 'snake'): array
{
    return test()->actingAs($player)->postJson("/api/v1/games/{$slug}/sessions")->assertCreated()->json('data');
}

function sendScore(User $player, string $sessionId, mixed $score, string $token = Play::TOKEN)
{
    return test()->actingAs($player)->postJson("/api/v1/game-sessions/{$sessionId}/score", ['score' => $score, 'token' => $token]);
}

it('inicia uma partida na versão no ar: token de uso único, URL do jogo e prazo', function (): void {
    $response = $this->actingAs($this->player)->postJson('/api/v1/games/snake/sessions')->assertCreated();
    $data = $response->json('data');
    $session = GameSession::query()->findOrFail($data['id']);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($data['token'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($data['play_url'])->toBe(config('publication.games_origin')."/{$this->version->public_id}/index.html")
        ->and($data['min_seconds'])->toBe(5)
        ->and($session->token_hash)->toBe(hash('sha256', $data['token']))
        ->and($session->status)->toBe(SessionStatus::Open)
        ->and($session->game_version_id)->toBe($this->version->id)
        ->and($session->user_id)->toBe($this->player->id)
        ->and($session->expires_at->diffInSeconds($session->started_at, true))->toBe(7200.0);
});

it('visitante não inicia partida; e-mail não confirmado também não (A5)', function (): void {
    $this->postJson('/api/v1/games/snake/sessions')->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())->postJson('/api/v1/games/snake/sessions')->assertForbidden();

    expect(GameSession::count())->toBe(0);
});

it('só há partida em jogo jogável (I9)', function (string $case): void {
    $slug = match ($case) {
        'inexistente' => 'nao-existe',
        'bloqueado' => Game::factory()->blocked()->create(['slug' => 'bloqueado'])->slug,
        'em análise' => Game::factory()->create(['slug' => 'em-analise'])->slug,
    };

    $this->actingAs($this->player)->postJson("/api/v1/games/{$slug}/sessions")
        ->assertUnprocessable()
        ->assertJson(['message' => 'Este jogo não está disponível para partidas no momento.']);
})->with(['inexistente', 'bloqueado', 'em análise']);

it('nova partida do mesmo jogo encerra a anterior: nunca duas abertas', function (): void {
    $first = startSession($this->player);
    $second = startSession($this->player);

    expect(GameSession::query()->findOrFail($first['id'])->status)->toBe(SessionStatus::Expired)
        ->and(GameSession::query()->findOrFail($second['id'])->status)->toBe(SessionStatus::Open);
    $this->travel(10)->seconds();
    sendScore($this->player, $first['id'], 10, $first['token'])
        ->assertUnprocessable()
        ->assertJson(['message' => 'Esta partida foi encerrada porque outra começou. Inicie outra para jogar de novo.']);
});

it('o banco recusa duas sessões abertas do mesmo jogador no mesmo jogo', function (): void {
    Play::openSession($this->player, $this->version);

    expect(fn () => Play::openSession($this->player, $this->version))->toThrow(QueryException::class);
});

it('registra o score e responde a posição no ranking', function (): void {
    $other = User::factory()->create();
    Play::score($other, $this->version, 500);
    $started = startSession($this->player);
    $this->travel(30)->seconds();

    sendScore($this->player, $started['id'], 120, $started['token'])
        ->assertCreated()
        ->assertExactJson(['data' => ['value' => 120, 'position' => 2, 'personal_best' => true, 'previous_best' => null]]);

    $score = Score::query()->where('user_id', $this->player->id)->sole();
    $session = GameSession::query()->findOrFail($started['id']);
    expect($score->value)->toBe(120)
        ->and($score->user_id)->toBe($this->player->id)
        ->and($score->game_version_id)->toBe($this->version->id)
        ->and($score->flagged)->toBeFalse()
        ->and($session->status)->toBe(SessionStatus::Scored)
        ->and($session->ended_at)->not->toBeNull();
});

it('informa se bateu o próprio recorde', function (): void {
    Play::score($this->player, $this->version, 200);
    $started = startSession($this->player);
    $this->travel(30)->seconds();

    sendScore($this->player, $started['id'], 150, $started['token'])
        ->assertCreated()
        ->assertJson(['data' => ['position' => 1, 'personal_best' => false, 'previous_best' => 200]]);
});

it('S1: reenviar o mesmo score (replay) falha com 409 e não cria outro registro', function (): void {
    $started = startSession($this->player);
    $this->travel(30)->seconds();

    sendScore($this->player, $started['id'], 120, $started['token'])->assertCreated();
    sendScore($this->player, $started['id'], 120, $started['token'])
        ->assertConflict()
        ->assertJson(['message' => 'Esta partida já registrou um score. Inicie outra para jogar de novo.']);
    sendScore($this->player, $started['id'], 999, $started['token'])->assertConflict();

    expect(Score::count())->toBe(1);
});

it('S1: o banco recusa um segundo score na mesma sessão', function (): void {
    $score = Play::score($this->player, $this->version, 10);

    expect(fn () => Score::factory()->create(['game_session_id' => $score->game_session_id]))->toThrow(QueryException::class);
});

it('S1: se outro envio gravou antes (corrida que escapou do lock), responde 409', function (): void {
    $session = Play::openSession($this->player, $this->version);
    // Simula o vencedor da corrida: o score existe, mas esta transação ainda vê a sessão aberta.
    Score::factory()->create(['game_session_id' => $session->id, 'value' => 1]);

    sendScore($this->player, $session->id, 2)->assertConflict();

    expect(Score::count())->toBe(1)
        ->and($session->fresh()?->status)->toBe(SessionStatus::Open);
});

it('I10: token errado, sessão de outro jogador ou inexistente não registram nada', function (): void {
    $session = Play::openSession($this->player, $this->version);
    $message = ['message' => 'Sessão de jogo inválida.'];

    sendScore($this->player, $session->id, 10, 'outro-token')->assertUnprocessable()->assertJson($message);
    sendScore(User::factory()->create(), $session->id, 10)->assertUnprocessable()->assertJson($message);
    sendScore($this->player, '01JAAAAAAAAAAAAAAAAAAAAAAA', 10)->assertUnprocessable()->assertJson($message);

    expect(Score::count())->toBe(0)
        ->and($session->fresh()?->status)->toBe(SessionStatus::Open);
});

it('I10: partida mais curta que o mínimo não registra score (e pode enviar depois)', function (): void {
    $started = startSession($this->player);
    $this->travel(4)->seconds();

    sendScore($this->player, $started['id'], 10, $started['token'])
        ->assertUnprocessable()
        ->assertJson(['message' => 'A partida durou menos que o mínimo para registrar o score.']);

    $this->travel(1)->seconds();
    sendScore($this->player, $started['id'], 10, $started['token'])->assertCreated();
});

it('I10: depois do prazo, a sessão não aceita score', function (): void {
    $started = startSession($this->player);
    $this->travel(7200)->seconds();

    sendScore($this->player, $started['id'], 10, $started['token'])
        ->assertUnprocessable()
        ->assertJson(['message' => 'O prazo desta partida acabou. Inicie outra para jogar de novo.']);
    expect(Score::count())->toBe(0);
});

it('S2: score fora da faixa, fracionário ou não inteiro é rejeitado, nunca arredondado', function (mixed $score): void {
    $session = Play::openSession($this->player, $this->version);

    sendScore($this->player, $session->id, $score)->assertUnprocessable()->assertJsonValidationErrors('score');
    expect(Score::count())->toBe(0);
})->with([
    'negativo' => -1,
    'acima do MAX' => 9_007_199_254_740_992,
    'fração' => 1.5,
    'string numérica' => '10',
    'nulo' => null,
    'booleano' => true,
    'lista' => [[1]],
]);

it('S2: número JSON com fração ou expoente chega como float e é rejeitado', function (string $json): void {
    $session = Play::openSession($this->player, $this->version);

    $this->actingAs($this->player)
        ->call('POST', "/api/v1/game-sessions/{$session->id}/score", server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"score":'.$json.',"token":"'.Play::TOKEN.'"}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('score');
    expect(Score::count())->toBe(0);
})->with(['2.0', '1e3', '9007199254740993.5']);

it('S2: aceita o maior score exato (Number.MAX_SAFE_INTEGER)', function (): void {
    $session = Play::openSession($this->player, $this->version);

    sendScore($this->player, $session->id, 9_007_199_254_740_991)->assertCreated()->assertJson(['data' => ['value' => 9_007_199_254_740_991]]);
});

it('S2: score muito acima do padrão do jogo é sinalizado e registrado, mas fica fora do ranking até a revisão (M20)', function (): void {
    foreach (range(1, 20) as $i) {
        Play::score(User::factory()->create(), $this->version, 100);
    }
    $session = Play::openSession($this->player, $this->version);

    sendScore($this->player, $session->id, 301)->assertCreated()->assertJson(['data' => ['position' => null]]);

    $score = Score::query()->where('user_id', $this->player->id)->sole();
    expect($score->flagged)->toBeTrue()
        ->and($score->flag_reason)->toBe('Acima de 3 × o p99 do jogo (100, com 20 scores).');
});

it('jogo bloqueado ou versão retirada durante a partida: o score não entra', function (string $case): void {
    $session = Play::openSession($this->player, $this->version);
    match ($case) {
        'bloqueado' => Game::query()->whereKey($this->game->id)->update(['status' => 'blocked', 'blocked_by_id' => User::factory()->admin()->create()->id, 'block_reason' => 'x', 'blocked_at' => now()]),
        'retirada' => GameVersion::query()->whereKey($this->version->id)->update(['status' => VersionStatus::Withdrawn, 'withdrawn_by_id' => User::factory()->admin()->create()->id, 'withdrawn_at' => now(), 'withdrawal_reason' => 'x']),
    };

    sendScore($this->player, $session->id, 10)->assertUnprocessable()->assertJson(['message' => 'Este jogo não está disponível para partidas no momento.']);
    expect(Score::count())->toBe(0);
})->with(['bloqueado', 'retirada']);

it('versão substituída durante a partida: o score vale, com a versão em que foi jogado', function (): void {
    $session = Play::openSession($this->player, $this->version);
    GameVersion::query()->whereKey($this->version->id)->update(['status' => VersionStatus::Superseded]);
    [, $v2] = Play::playableGame($this->game, 2);

    sendScore($this->player, $session->id, 10)->assertCreated();
    expect(Score::sole()->game_version_id)->toBe($this->version->id)->not->toBe($v2->id);
});

it('S2: limita as partidas por jogador e jogo', function (): void {
    config(['play.sessions_per_hour_per_game' => 3]);

    foreach (range(1, 3) as $i) {
        startSession($this->player);
    }
    $this->actingAs($this->player)->postJson('/api/v1/games/snake/sessions')->assertTooManyRequests();
});

it('o jogo nunca recebe o token: a URL do jogo não carrega nada da sessão', function (): void {
    $data = startSession($this->player);

    expect($data['play_url'])->not->toContain($data['token'])->not->toContain($data['id'])->not->toContain('?');
});

it('o prazo da partida é de 2 h a partir do início, no relógio do servidor', function (): void {
    Carbon::setTestNow('2026-10-13 12:00:00');
    $data = startSession($this->player);

    expect($data['started_at'])->toBe('2026-10-13T12:00:00+00:00')
        ->and($data['expires_at'])->toBe('2026-10-13T14:00:00+00:00');
});
