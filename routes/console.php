<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Envía alertas a radiólogos con órdenes pendientes — cada día a las 8:00 AM
Schedule::command('alerta:mail')->dailyAt('08:00');

// Sincroniza feriados de Chile desde api.boostr.cl — cada 2 de enero a las 06:00
Schedule::command('feriados:sync')->yearlyOn(1, 2, '06:00');
