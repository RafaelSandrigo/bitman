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
