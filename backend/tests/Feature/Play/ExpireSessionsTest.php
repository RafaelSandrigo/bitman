<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\GuestPlay;
use App\Modules\Play\Domain\SessionStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Tests\Support\Play;

/*
| M14: sessões vencidas viram `expired` no banco (o estado reflete a regra de I10), e partidas de
| visitante sem score para salvar são apagadas.
*/

beforeEach(function (): void {
    [$this->game, $this->version] = Play::playableGame();
    $this->player = User::factory()->create();
});

it('marca expired as sessões abertas depois do prazo, com o fim no prazo', function (): void {
    $overdue = Play::openSession($this->player, $this->version);
    $overdue->forceFill(['started_at' => now()->subHours(3), 'expires_at' => now()->subHour()])->save();
    [, $otherVersion] = Play::playableGame();
    $open = Play::openSession($this->player, $otherVersion);
    Log::spy();

    $this->artisan('bitman:expire-sessions')->expectsOutputToContain('1 sessão(ões) expirada(s)')->assertSuccessful();

    $overdue->refresh();
    expect($overdue->status)->toBe(SessionStatus::Expired)
        ->and($overdue->ended_at?->equalTo($overdue->expires_at))->toBeTrue()
        ->and($open->fresh()?->status)->toBe(SessionStatus::Open);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'play.sessions_expired' && $context['sessions'] === 1);
});

it('apaga partidas de visitante que não têm mais score para salvar', function (): void {
    $base = ['game_id' => $this->game->id, 'game_version_id' => $this->version->id, 'token_hash' => hash('sha256', 'x')];
    $abandoned = GuestPlay::query()->forceCreate([...$base, 'started_at' => now()->subDays(2), 'expires_at' => now()->subDays(2)->addHours(2)]);
    $lapsed = GuestPlay::query()->forceCreate([...$base, 'started_at' => now()->subDays(3), 'expires_at' => now()->subDays(3)->addHours(2), 'value' => 10, 'ended_at' => now()->subDays(3), 'claim_expires_at' => now()->subDays(2)]);
    $pending = GuestPlay::query()->forceCreate([...$base, 'started_at' => now()->subHour(), 'expires_at' => now()->addHour(), 'value' => 10, 'ended_at' => now()->subMinutes(50), 'claim_expires_at' => now()->addHours(23)]);
    $playing = GuestPlay::query()->forceCreate([...$base, 'started_at' => now()->subMinutes(5), 'expires_at' => now()->addHour()]);

    $this->artisan('bitman:expire-sessions')->assertSuccessful();

    expect(GuestPlay::query()->pluck('id')->sort()->values()->all())->toBe(collect([$pending->id, $playing->id])->sort()->values()->all())
        ->and(GuestPlay::query()->find($abandoned->id))->toBeNull()
        ->and(GuestPlay::query()->find($lapsed->id))->toBeNull();
});

it('roda a cada 15 minutos', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event): bool => str_contains((string) $event->command, 'bitman:expire-sessions'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('*/15 * * * *');
});
