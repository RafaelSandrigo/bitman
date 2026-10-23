<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure\Console;

use App\Modules\Play\Application\Actions\ExpireOverdueSessions;
use Illuminate\Console\Command;

/** M14: agendado a cada 15 minutos (routes/console.php). */
final class ExpireSessionsCommand extends Command
{
    protected $signature = 'bitman:expire-sessions';

    protected $description = 'Marca expired as sessões vencidas e apaga partidas de visitante sem score para salvar';

    public function handle(ExpireOverdueSessions $expire): int
    {
        $result = $expire();
        $this->line(sprintf('expire-sessions: %d sessão(ões) expirada(s), %d partida(s) de visitante apagada(s)', $result['sessions'], $result['guest_plays']));

        return self::SUCCESS;
    }
}
