<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\Events\CommitRejected;
use App\Modules\Versioning\Domain\Events\ImportRejected;
use App\Modules\Versioning\Domain\Events\RepositoryFetched;
use App\Modules\Versioning\Domain\Events\RepositoryFetchFailed;
use App\Modules\Versioning\Domain\Events\VersionPublished;
use App\Modules\Versioning\Domain\Events\VersionRejected;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Infrastructure\Notifications\AdminDigestNotification;
use App\Modules\Versioning\Infrastructure\Notifications\VersionOutcomeNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

/*
| M3: e-mail ao dono quando a versão para ou vai ao ar, e resumo diário ao admin das filas.
*/

beforeEach(function (): void {
    Notification::fake();
    $this->owner = User::factory()->developer()->create();
    $this->game = Game::factory()->create(['owner_id' => $this->owner->id, 'name' => 'Snake']);
    $this->admin = User::factory()->admin()->create();
});

/** O evento é auditado: precisa de uma versão de verdade. */
function versionOf(Game $game): string
{
    return GameVersion::factory()->for($game)->create(['number' => 2])->id;
}

/** @return array{string, string} assunto e texto do e-mail enviado ao dono */
function ownerMail(User $owner): array
{
    $sent = null;
    Notification::assertSentTo($owner, VersionOutcomeNotification::class, function (VersionOutcomeNotification $notification) use ($owner, &$sent): bool {
        $mail = $notification->toMail($owner);
        $sent = [$mail->subject, implode("\n", $mail->introLines)];

        return true;
    });

    return $sent ?? ['', ''];
}

it('avisa o dono quando a versão para, com o motivo', function (object $event, string $subject, string $text): void {
    event($event);

    [$sentSubject, $sentText] = ownerMail($this->owner);
    expect($sentSubject)->toBe($subject)->and($sentText)->toContain($text);
})->with([
    'importação recusada' => [fn () => new ImportRejected(versionOf(test()->game), test()->game->id, 2, 'https://github.com/a/b', 'main', test()->admin->id, 'Repositório errado.'), 'Snake v2: importação recusada', 'Repositório errado.'],
    'commit rejeitado' => [fn () => new CommitRejected(versionOf(test()->game), test()->game->id, 2, str_repeat('a', 40), test()->admin->id, 'Commit com binário.'), 'Snake v2: commit rejeitado', 'Commit com binário.'],
    'versão rejeitada' => [fn () => new VersionRejected(versionOf(test()->game), test()->game->id, 2, test()->admin->id, 'Minerador no game.js.'), 'Snake v2: versão rejeitada', 'Minerador no game.js.'],
]);

it('avisa a falha da busca e a reprovação na validação, com a versão', function (): void {
    $failed = GameVersion::factory()->for($this->game)->fetchFailed()->create(['number' => 3]);
    event(new RepositoryFetchFailed($failed->id, $this->game->id, 'ref_not_found'));

    [$subject, $text] = ownerMail($this->owner);
    expect($subject)->toBe('Snake v3: a busca do repositório falhou')->and($text)->toContain('A branch ou tag não existe no repositório.');
});

it('reprovada na validação avisa; busca sem violação bloqueante não', function (): void {
    $version = GameVersion::factory()->for($this->game)->validationFailed()->create(['number' => 4]);
    event(new RepositoryFetched($version->id, $this->game->id, str_repeat('b', 40), false, 0, 3));
    Notification::assertNothingSent();

    event(new RepositoryFetched($version->id, $this->game->id, str_repeat('b', 40), true, 2, 3));
    [$subject] = ownerMail($this->owner);
    expect($subject)->toBe('Snake v4: reprovada na validação');
});

it('avisa quando a versão vai ao ar', function (): void {
    event(new VersionPublished(versionOf($this->game), $this->game->id, 5, '11111111-2222-4333-8444-555555555555', str_repeat('c', 40), [], $this->admin->id));

    [$subject] = ownerMail($this->owner);
    expect($subject)->toBe('Snake v5 está no ar');
});

it('dono sem e-mail confirmado não recebe nada', function (): void {
    $this->owner->forceFill(['email_verified_at' => null])->save();

    event(new VersionRejected(versionOf($this->game), $this->game->id, 2, $this->admin->id, 'Motivo.'));

    Notification::assertNothingSent();
});

it('resumo diário ao admin com as filas que esperam decisão', function (): void {
    GameVersion::factory()->for($this->game)->create(['number' => 1]);
    GameVersion::factory()->for(Game::factory())->awaitingFileReview()->create();

    $this->artisan('bitman:admin-digest')->assertSuccessful();

    Notification::assertSentTo($this->admin, AdminDigestNotification::class, function (AdminDigestNotification $notification): bool {
        $lines = implode("\n", $notification->toMail($this->admin)->introLines);

        return str_contains($lines, 'Portão 0 (autorizar importação): 1') && str_contains($lines, 'Portão 2 (revisar arquivos): 1');
    });
    Notification::assertNotSentTo($this->owner, AdminDigestNotification::class);
});

it('sem nada pendente, não manda resumo; roda todo dia', function (): void {
    $this->artisan('bitman:admin-digest')->assertSuccessful();
    Notification::assertNothingSent();

    $events = collect(app(Schedule::class)->events())->filter(fn ($event): bool => str_contains((string) $event->command, 'bitman:admin-digest'));
    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 12 * * *');
});
