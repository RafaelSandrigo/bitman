<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// M12: o served/ confere com as versões no ar; corrige a partir do archive e alerta nos logs.
Schedule::command('bitman:reconcile-served')->hourly()->withoutOverlapping();

// M3: resumo das filas do admin, todo dia às 9h de Brasília (12h UTC). Só sai se houver pendências.
Schedule::command('bitman:admin-digest')->dailyAt('12:00');

// M14: sessões vencidas viram expired; partidas de visitante sem score para salvar são apagadas.
Schedule::command('bitman:expire-sessions')->everyFifteenMinutes()->withoutOverlapping();
