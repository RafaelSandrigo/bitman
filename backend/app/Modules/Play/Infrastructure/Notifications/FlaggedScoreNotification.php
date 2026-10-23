<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure\Notifications;

use App\Modules\Identity\Domain\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * M21: um score foi sinalizado e espera a revisão. Pela fila, como os outros e-mails. Não cita o
 * jogador nem o motivo: o e-mail só chama para a moderação, onde o admin vê os detalhes.
 */
final class FlaggedScoreNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $gameName,
        public readonly int $value,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Score sinalizado em {$this->gameName}")
            ->line(sprintf('Um score de %s pontos em %s ficou muito acima do padrão do jogo.', number_format($this->value, 0, ',', '.'), $this->gameName))
            ->line('Ele está fora do ranking até você decidir: manter no ranking ou ocultar.')
            ->action('Revisar scores', rtrim((string) config('app.url'), '/').'/admin/scores')
            ->line('Novos sinalizados deste jogo na próxima hora não geram outro e-mail.');
    }
}
