<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Notifications;

use App\Modules\Identity\Domain\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** M3: resumo diário das filas que esperam uma decisão do admin. */
final class AdminDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, int>  $queues  rótulo da fila => quantas versões esperam */
    public function __construct(public readonly array $queues) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(sprintf('Bitman: %d versão(ões) esperando decisão', array_sum($this->queues)));
        foreach ($this->queues as $label => $count) {
            $mail->line("{$label}: {$count}");
        }

        return $mail->action('Abrir o painel', rtrim((string) config('app.url'), '/').'/admin');
    }
}
