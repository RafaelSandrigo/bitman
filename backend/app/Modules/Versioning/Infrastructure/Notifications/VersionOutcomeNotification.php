<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Notifications;

use App\Modules\Identity\Domain\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** M3: o que aconteceu com uma versão do jogo do dono (parou ou foi ao ar). Pela fila, como os outros e-mails. */
final class VersionOutcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $summary,
        public readonly ?string $detail,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->line($this->summary);
        if ($this->detail !== null) {
            $mail->line($this->detail);
        }

        return $mail->action('Ver meus jogos', rtrim((string) config('app.url'), '/').'/desenvolvedor/jogos');
    }
}
