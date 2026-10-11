<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Notifications\VerifyEmailNotification;
use App\Modules\Identity\Domain\User;
use Illuminate\Support\Facades\Notification;

/** Extrai, do e-mail enviado, o link da API que a SPA chama em /verificar-email. */
function verificationLinkFor(User $user): string
{
    $link = null;
    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $n) use ($user, &$link): bool {
        parse_str((string) parse_url($n->toMail($user)->actionUrl, PHP_URL_QUERY), $query);
        $link = $query['link'];

        return true;
    });

    return $link;
}

it('verifica o e-mail pelo link assinado chamado pela SPA', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    $this->actingAs($user)->getJson(verificationLinkFor($user))->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('recusa link adulterado', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $tampered = str_replace('signature=', 'signature=0', verificationLinkFor($user));

    $this->actingAs($user)->getJson($tampered)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('não deixa um usuário verificar o e-mail de outro', function (): void {
    Notification::fake();
    $victim = User::factory()->unverified()->create();
    $victim->sendEmailVerificationNotification();
    $attacker = User::factory()->unverified()->create();

    $this->actingAs($attacker)->getJson(verificationLinkFor($victim))->assertForbidden();

    expect($victim->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('reenvia a verificação com rate limit (A5)', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    foreach (range(1, 6) as $_) {
        $this->actingAs($user)->postJson('/api/v1/auth/email/verification-notification')->assertStatus(202);
    }
    $this->actingAs($user)->postJson('/api/v1/auth/email/verification-notification')->assertTooManyRequests();

    Notification::assertSentToTimes($user, VerifyEmailNotification::class, 6);
});
