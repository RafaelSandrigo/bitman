<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Console;

use App\Modules\Versioning\Application\Actions\ReconcileServedVersions;
use Illuminate\Console\Command;

/** M12: agendado de hora em hora (routes/console.php). Falha (exit 1) se algo que devia estar no ar não voltou. */
final class ReconcileServedCommand extends Command
{
    protected $signature = 'bitman:reconcile-served';

    protected $description = 'Confere o served/ contra as versões publicadas e corrige a partir do archive';

    public function handle(ReconcileServedVersions $reconcile): int
    {
        $report = $reconcile();

        $this->line(sprintf(
            'reconcile-served: %s; %d de volta, %d trocada(s), %d retirada(s), %d falha(s)',
            $report->corrections() === 0 ? 'nenhuma correção' : "{$report->corrections()} correção(ões)",
            count($report->restored),
            count($report->replaced),
            count($report->removed),
            count($report->failed),
        ));

        return $report->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
