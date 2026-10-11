<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Enviada pela fila: uma falha do SMTP não derruba o cadastro. A URL aponta para a SPA
 * (configurada no IdentityServiceProvider) e o texto vem de lang/pt_BR.json.
 */
final class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
