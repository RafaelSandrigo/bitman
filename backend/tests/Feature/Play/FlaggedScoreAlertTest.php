<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Events\ScoreRecorded;
use App\Modules\Play\Infrastructure\Notifications\FlaggedScoreNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Play;

/*
| M21: o admin fica sabendo dos scores sinalizados (S2) pelo painel e por e-mail.
*/

beforeEach(function (): void {
    [$this->game, $this->version] = Play::playableGame();
    $this->admin = User::factory()->admin()->create();
    $this->player = User::factory()->create();
});

it('conta os sinalizados em revisão para o painel', function (): void {
    Play::score($this->player, $this->version, 900, ['flagged' => true, 'flag_reason' => 'x']);
    Play::score($this->player, $this->version, 800, ['flagged' => true, 'flag_reason' => 'x', 'flag_cleared_by_id' => $this->admin->id, 'flag_cleared_at' => now()]);
    Play::score($this->player, $this->version, 10);

    $this->actingAs($this->admin)->getJson('/api/v1/admin/scores/summary')->assertOk()->assertExactJson(['data' => ['in_review' => 1]]);
    $this->actingAs($this->player)->getJson('/api/v1/admin/scores/summary')->assertForbidden();
});

it('score sinalizado avisa por e-mail quem modera scores, sem citar o jogador', function (): void {
    Notification::fake();
    $score = Play::score($this->player, $this->version, 900, ['flagged' => true, 'flag_reason' => 'x']);

    event(new ScoreRecorded($score->id, $this->game->id, $this->player->id, 900, true));

    Notification::assertSentTo($this->admin, FlaggedScoreNotification::class, function (FlaggedScoreNotification $notification): bool {
        $mail = $notification->toMail($this->admin);

        return $mail->subject === "Score sinalizado em {$this->game->name}"
            && $mail->actionUrl === rtrim((string) config('app.url'), '/').'/admin/scores'
            && ! str_contains(implode(' ', $mail->introLines), $this->player->name);
    });
    Notification::assertNotSentTo($this->player, FlaggedScoreNotification::class);
});

it('score comum não avisa ninguém', function (): void {
    Notification::fake();
    $score = Play::score($this->player, $this->version, 10);

    event(new ScoreRecorded($score->id, $this->game->id, $this->player->id, 10, false));

    Notification::assertNothingSent();
});

it('no máximo um e-mail por jogo por hora: uma rajada de sinalizados não vira spam', function (): void {
    Notification::fake();
    foreach (range(1, 3) as $value) {
        $score = Play::score($this->player, $this->version, 900 + $value, ['flagged' => true, 'flag_reason' => 'x']);
        event(new ScoreRecorded($score->id, $this->game->id, $this->player->id, 900 + $value, true));
    }

    Notification::assertSentToTimes($this->admin, FlaggedScoreNotification::class, 1);
    $this->travel(61)->minutes();
    event(new ScoreRecorded($score->id, $this->game->id, $this->player->id, 999, true));
    Notification::assertSentToTimes($this->admin, FlaggedScoreNotification::class, 2);
});
