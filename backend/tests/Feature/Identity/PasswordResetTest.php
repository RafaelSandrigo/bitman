<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Domain\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('envia o link de redefinição apontando para a SPA', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ada@example.com'])->assertOk();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) use ($user): bool {
        return str_starts_with($n->toMail($user)->actionUrl, config('app.url').'/redefinir-senha?token=');
    });
});

it('redefine a senha com o token recebido', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ada@example.com']);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ada@example.com']);

    $token = null;
    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) use (&$token): bool {
        $token = $n->token;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'ada@example.com',
        'password' => 'nova-senha-2',
        'password_confirmation' => 'nova-senha-2',
    ])->assertOk();

    expect(Hash::check('nova-senha-2', $user->fresh()->getAuthPassword()))->toBeTrue();
});

it('recusa token inválido', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalido',
        'email' => 'ada@example.com',
        'password' => 'nova-senha-2',
        'password_confirmation' => 'nova-senha-2',
    ])->assertUnprocessable();

    expect(Hash::check('password', $user->fresh()->getAuthPassword()))->toBeTrue();
});

it('responde igual para e-mail cadastrado, desconhecido ou repetido (sem enumeração de contas)', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'ada@example.com']);

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ada@example.com']);
    $repeated = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ada@example.com']);
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ninguem@example.com']);

    foreach ([$known, $repeated, $unknown] as $response) {
        $response->assertOk()->assertExactJson($known->json());
    }
});
