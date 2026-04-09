<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FeriadosSync extends Command
{
    protected $signature   = 'feriados:sync {--year= : Año a sincronizar (default: año actual y siguiente)}';
    protected $description = 'Sincroniza los feriados de Chile desde api.boostr.cl hacia la tabla feriados';

    public function handle(): int
    {
        $yearArg = $this->option('year');
        $years   = $yearArg
            ? [(int) $yearArg]
            : [now()->year, now()->addYear()->year];

        $total = 0;

        foreach ($years as $year) {
            $this->info("Sincronizando feriados del año $year...");

            $response = Http::withOptions([
                'verify'  => app()->isProduction(),
                'timeout' => 15,
            ])->get('https://api.boostr.cl/holidays.json', ['year' => $year]);

            if (! $response->successful()) {
                $this->error("Error al consultar la API para el año $year: HTTP {$response->status()}");
                continue;
            }

            $data = $response->json('data', []);

            if (empty($data)) {
                $this->warn("No se recibieron feriados para el año $year.");
                continue;
            }

            $inserted = 0;
            $updated  = 0;

            foreach ($data as $feriado) {
                $fecha       = $feriado['date']  ?? null;
                $descripcion = $feriado['title'] ?? null;

                if (! $fecha || ! $descripcion) continue;

                $exists = DB::table('feriados')->where('fecha', $fecha)->exists();

                DB::table('feriados')->updateOrInsert(
                    ['fecha'       => $fecha],
                    ['descripcion' => $descripcion]
                );

                $exists ? $updated++ : $inserted++;
            }

            $this->info("  Año $year: $inserted insertados, $updated actualizados.");
            $total += $inserted + $updated;
        }

        $this->info("Sincronización completada. Total procesados: $total feriados.");

        return Command::SUCCESS;
    }
}
