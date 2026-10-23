<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Console;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\AwaitingAdmin;
use App\Modules\Versioning\Infrastructure\Notifications\AdminDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/** M3: agendado todo dia (routes/console.php). Só manda se alguma fila tem versão esperando. */
final class AdminDigestCommand extends Command
{
    protected $signature = 'bitman:admin-digest';

    protected $description = 'Manda ao admin o resumo das versões esperando decisão';

    public function handle(AwaitingAdmin $awaiting): int
    {
        $queues = $awaiting->queues();
        if ($queues === []) {
            $this->line('admin-digest: nada pendente');

            return self::SUCCESS;
        }

        $admins = User::permission(Permission::AuthorizeImports->value)->whereNotNull('email_verified_at')->get();
        Notification::send($admins, new AdminDigestNotification($queues));
        $this->line(sprintf('admin-digest: %d versão(ões) pendente(s), %d destinatário(s)', array_sum($queues), $admins->count()));

        return self::SUCCESS;
    }
}
