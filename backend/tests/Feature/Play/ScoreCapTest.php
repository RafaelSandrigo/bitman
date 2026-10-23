<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Catalog\Application\Actions\ChangeScoreCap;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Score;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Play;

/*
| M15: o admin define um teto opcional de score por jogo; acima dele, o score é sinalizado (S2).
| A duração da partida também conta: pontos por segundo fora do padrão do jogo são sinalizados.
*/

beforeEach(function (): void {
    [$this->game, $this->version] = Play::playableGame();
    $this->admin = User::factory()->admin()->create();
    $this->player = User::factory()->create();
});

it('o admin define e remove o teto, auditado', function (): void {
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/games/{$this->game->id}/score-cap", ['score_cap' => 5000])
        ->assertOk()
        ->assertJsonPath('data.score_cap', 5000);
    $this->patchJson("/api/v1/admin/games/{$this->game->id}/score-cap", ['score_cap' => null])->assertOk()->assertJsonPath('data.score_cap', null);

    $entries = AuditEntry::query()->where('action', 'catalog.score-cap-changed')->orderBy('id')->get();
    expect($entries)->toHaveCount(2)
        ->and($entries[0]->context)->toMatchArray(['from' => null, 'to' => 5000])
        ->and($entries[1]->context)->toMatchArray(['from' => 5000, 'to' => null]);
});

it('o teto é um inteiro na faixa do score', function (mixed $cap): void {
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/games/{$this->game->id}/score-cap", ['score_cap' => $cap])->assertJsonValidationErrors('score_cap');
})->with(['zero' => 0, 'fração' => 1.5, 'texto' => '100', 'acima do máximo' => 9_007_199_254_740_992]);

it('a Action exige scores.moderate', function (): void {
    expect(fn () => app(ChangeScoreCap::class)($this->player, $this->game->id, 10))->toThrow(AuthorizationException::class);
});

it('score acima do teto é sinalizado já no primeiro envio', function (): void {
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/games/{$this->game->id}/score-cap", ['score_cap' => 1000])->assertOk();
    $session = Play::openSession($this->player, $this->version);

    $this->actingAs($this->player)->postJson("/api/v1/game-sessions/{$session->id}/score", ['score' => 1001, 'token' => Play::TOKEN])->assertCreated();

    $score = Score::query()->sole();
    expect($score->flagged)->toBeTrue()->and($score->flag_reason)->toBe('Acima do teto do jogo (1000).');
});

it('partida curta com pontos demais para a duração é sinalizada', function (): void {
    // 20 partidas de 10 minutos com 600 pontos: 1 ponto por segundo.
    foreach (range(1, 20) as $ignored) {
        Play::score(User::factory()->create(), $this->version, 600);
    }
    $session = Play::openSession($this->player, $this->version);
    $session->forceFill(['started_at' => now()->subSeconds(10)])->save();

    // 500 pontos em 10 s = 50/s, acima de 3 × 1/s; o valor em si está abaixo de 3 × p99 (1800).
    $this->actingAs($this->player)->postJson("/api/v1/game-sessions/{$session->id}/score", ['score' => 500, 'token' => Play::TOKEN])->assertCreated();

    expect(Score::query()->where('user_id', $this->player->id)->sole()->flag_reason)->toStartWith('Acima de 3 × o p99 de pontos por segundo do jogo');
});
