<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Infrastructure\Console\GrantRoleCommand;
use App\Modules\Identity\Infrastructure\Fortify\CreateNewUser;
use App\Modules\Identity\Infrastructure\Fortify\PasswordResetLinkRequestedResponse;
use App\Modules\Identity\Infrastructure\Fortify\ResetUserPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestedResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkRequestedResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Password::defaults(fn () => Password::min(8)->max(128)->letters()->numbers());

        $this->configureRateLimits();
        $this->configureEmailLinks();

        if ($this->app->runningInConsole()) {
            $this->commands([GrantRoleCommand::class]);
        }
    }

    /** A2: força bruta no login limitada por e-mail + IP e, em volume, só por IP. */
    private function configureRateLimits(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::transliterate(Str::lower($request->string('email')->toString()));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by('login|'.$request->ip()),
            ];
        });

        // M18: pedidos para ser developer (um pendente por vez; o limite cobre os repetidos após rejeição).
        RateLimiter::for('developer-requests', fn (Request $request): Limit => Limit::perHour(5)->by('developer-requests|'.$request->user()?->getAuthIdentifier()));
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(30)->by('auth|'.$request->ip()));
    }

    /**
     * Os links dos e-mails abrem a SPA, que conclui a operação pela API. O link de verificação
     * carrega a URL assinada gerada pelo framework (só path + query; a SPA a chama na mesma origem).
     */
    private function configureEmailLinks(): void
    {
        VerifyEmail::toMailUsing(function (User $user, string $signedUrl): MailMessage {
            $parts = parse_url($signedUrl);
            $link = ($parts['path'] ?? '').'?'.($parts['query'] ?? '');

            return (new MailMessage)
                ->subject('Confirme seu e-mail no Bitman')
                ->greeting("Olá, {$user->name}!")
                ->line('Confirme seu e-mail para jogar e aparecer nos rankings do Bitman.')
                ->action('Confirmar e-mail', $this->spaUrl('/verificar-email', ['link' => $link]))
                ->line('O link vale por 60 minutos. Se você não criou uma conta, ignore este e-mail.');
        });

        ResetPassword::toMailUsing(fn (User $user, string $token): MailMessage => (new MailMessage)
            ->subject('Redefinição de senha do Bitman')
            ->greeting("Olá, {$user->name}!")
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Redefinir senha', $this->spaUrl('/redefinir-senha', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]))
            ->line('O link vale por 60 minutos. Se você não pediu a redefinição, ignore este e-mail.'));
    }

    /** @param  array<string, string>  $query */
    private function spaUrl(string $path, array $query): string
    {
        return rtrim((string) config('app.url'), '/').$path.'?'.http_build_query($query);
    }
}
