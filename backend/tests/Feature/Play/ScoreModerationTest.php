<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\Actions\HideScore;
use App\Modules\Play\Domain\Score;
use App\Modules\Versioning\Domain\DecisionReason;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Play;

/*
| S2: o admin vê os scores sinalizados e os oculta do ranking (ou devolve), com motivo e auditoria.
*/

beforeEach(function (): void {
    [$this->game, $this->version] = Play::playableGame();
    $this->admin = User::factory()->admin()->create(['name' => 'Rafael']);
    $this->player = User::factory()->create(['name' => 'Ana']);
});

it('lista os sinalizados ainda no ranking, por padrão', function (): void {
    $flagged = Play::score($this->player, $this->version, 900, ['flagged' => true, 'flag_reason' => 'Fora do padrão.']);
    Play::score($this->player, $this->version, 10);
    Play::score($this->player, $this->version, 800, ['flagged' => true, 'flag_reason' => 'x', 'hidden_by_id' => $this->admin->id, 'hidden_at' => now(), 'hide_reason' => 'Forjado.']);

    $this->actingAs($this->admin)->getJson('/api/v1/admin/scores')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $flagged->id)
        ->assertJsonPath('data.0.player', ['id' => $this->player->id, 'name' => 'Ana'])
        ->assertJsonPath('data.0.game.slug', $this->game->slug)
        ->assertJsonPath('data.0.flag_reason', 'Fora do padrão.')
        ->assertJsonPath('data.0.hidden', null)
        ->assertJsonPath('data.0.session_seconds', 600);
});

it('filtra os ocultos, todos e por jogo', function (): void {
    Play::score($this->player, $this->version, 10);
    Play::score($this->player, $this->version, 20, ['hidden_by_id' => $this->admin->id, 'hidden_at' => now(), 'hide_reason' => 'Forjado.']);
    [, $otherVersion] = Play::playableGame();
    Play::score($this->player, $otherVersion, 30);

    $this->actingAs($this->admin)->getJson('/api/v1/admin/scores?filter=hidden')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.hidden', ['by' => 'Rafael', 'at' => $this->getJson('/api/v1/admin/scores?filter=hidden')->json('data.0.hidden.at'), 'reason' => 'Forjado.']);
    $this->getJson('/api/v1/admin/scores?filter=all')->assertJsonCount(3, 'data');
    $this->getJson('/api/v1/admin/scores?filter=all&game_id='.$this->game->id)->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/admin/scores?filter=outro')->assertUnprocessable();
});

it('oculta com motivo: sai do ranking, continua registrado e auditado (I11)', function (): void {
    $score = Play::score($this->player, $this->version, 900, ['flagged' => true, 'flag_reason' => 'Fora do padrão.']);
    Play::score(User::factory()->create(['name' => 'Bia']), $this->version, 10);

    $this->actingAs($this->admin)->postJson("/api/v1/admin/scores/{$score->id}/hide", ['reason' => 'Score impossível para o jogo.'])
        ->assertOk()
        ->assertJsonPath('data.hidden.reason', 'Score impossível para o jogo.');

    expect($score->fresh()?->isHidden())->toBeTrue()
        ->and(array_column($this->getJson("/api/v1/games/{$this->game->slug}/leaderboard")->json('data'), 'player_name'))->toBe(['Bia']);
    $entry = AuditEntry::sole();
    expect($entry->action)->toBe('play.score-hidden')
        ->and($entry->subject_type)->toBe('score')
        ->and($entry->subject_id)->toBe($score->id)
        ->and($entry->actor_id)->toBe($this->admin->id)
        ->and($entry->reason)->toBe('Score impossível para o jogo.')
        ->and($entry->context)->toMatchArray(['game_id' => $this->game->id, 'player_id' => $this->player->id, 'value' => 900, 'flagged' => true]);
});

it('ocultar exige motivo e não repete', function (): void {
    $score = Play::score($this->player, $this->version, 900);

    $this->actingAs($this->admin)->postJson("/api/v1/admin/scores/{$score->id}/hide", ['reason' => ''])->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/admin/scores/{$score->id}/hide", ['reason' => 'Forjado.'])->assertOk();
    $this->postJson("/api/v1/admin/scores/{$score->id}/hide", ['reason' => 'De novo.'])
        ->assertUnprocessable()
        ->assertJson(['message' => 'Este score já está oculto do ranking.']);
});

it('devolve ao ranking, auditado', function (): void {
    $score = Play::score($this->player, $this->version, 900);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/scores/{$score->id}/hide", ['reason' => 'Engano.'])->assertOk();

    $this->postJson("/api/v1/admin/scores/{$score->id}/restore")->assertOk()->assertJsonPath('data.hidden', null);
    $this->postJson("/api/v1/admin/scores/{$score->id}/restore")->assertUnprocessable();

    expect(AuditEntry::query()->orderBy('id')->pluck('action')->all())->toBe(['play.score-hidden', 'play.score-restored'])
        ->and(array_column($this->getJson("/api/v1/games/{$this->game->slug}/leaderboard")->json('data'), 'value'))->toBe([900]);
});

it('a Action exige a permissão scores.moderate (defesa em profundidade)', function (): void {
    $score = Play::score($this->player, $this->version, 900);

    expect(fn () => app(HideScore::class)($this->player, $score->id, DecisionReason::fromString('Forjado.')))
        ->toThrow(AuthorizationException::class)
        ->and(Score::query()->whereNotNull('hidden_at')->count())->toBe(0);
});
