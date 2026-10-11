<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Notifications\VerifyEmailNotification;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

function registration(array $overrides = []): array
{
    return [
        'name' => 'Ada Lovelace',
        'email' => 'Ada@Example.com',
        'password' => 'senha-forte-1',
        'password_confirmation' => 'senha-forte-1',
        ...$overrides,
    ];
}

it('cadastra qualquer pessoa como player, com e-mail em minúsculas e sessão iniciada', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', registration())->assertCreated();

    $user = User::sole();
    expect($user->email)->toBe('ada@example.com')
        ->and($user->roleList())->toBe([Role::Player])
        ->and($user->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

it('envia a verificação de e-mail pela fila, com link para a SPA (A5)', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', registration())->assertCreated();

    Notification::assertSentTo(User::sole(), VerifyEmailNotification::class, function (VerifyEmailNotification $notification, array $channels, User $user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return $notification instanceof ShouldQueue
            && str_starts_with($url, config('app.url').'/verificar-email?link=')
            && str_contains(urldecode($url), '/api/v1/auth/email/verify/'.$user->id.'/');
    });
});

it('rejeita dados inválidos sem criar a conta', function (array $overrides, string $field): void {
    $this->postJson('/api/v1/auth/register', registration($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(User::count())->toBe(0);
})->with([
    'nome vazio' => [['name' => ''], 'name'],
    'e-mail inválido' => [['email' => 'nao-e-email'], 'email'],
    'senha curta' => [['password' => 'a1', 'password_confirmation' => 'a1'], 'password'],
    'senha sem número' => [['password' => 'somenteletras', 'password_confirmation' => 'somenteletras'], 'password'],
    'confirmação diferente' => [['password_confirmation' => 'outra-senha-1'], 'password'],
]);

it('não aceita e-mail já cadastrado, sem diferenciar maiúsculas', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/auth/register', registration(['email' => 'ADA@example.com']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('ignora campos extras como roles e email_verified_at (A3)', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', registration([
        'roles' => ['admin'],
        'email_verified_at' => now()->toIso8601String(),
    ]))->assertCreated();

    $user = User::sole();
    expect($user->roleList())->toBe([Role::Player])
        ->and($user->hasVerifiedEmail())->toBeFalse();
});
