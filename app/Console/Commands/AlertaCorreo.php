<?php

namespace App\Console\Commands;

use App\Mail\AlertaRadiologo;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AlertaCorreo extends Command
{
    protected $signature   = 'alerta:mail {--dry-run : Muestra los correos sin enviarlos}';
    protected $description = 'Envía alertas por correo a radiólogos con órdenes pendientes de informe';

    public function handle(): int
    {
        // Órdenes enviadas, no respondidas, con radiólogo asignado
        $rows = DB::table('orders as o')
            ->join('order_staff_exam as ose', 'ose.order_id', '=', 'o.id')
            ->join('staffs as s', 's.id', '=', 'ose.staff_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('patients as p', 'p.id', '=', 'o.patient_id')
            ->where('o.estadoradiologo', 0)
            ->whereNotNull('o.enviada')
            ->whereNotNull('u.mail')
            ->where('u.mail', '!=', '')
            ->select(
                'o.id',
                'o.enviada',
                'p.name as paciente',
                'u.mail as email',
                'u.name as radiologo',
            )
            ->orderBy('u.mail')
            ->orderBy('o.enviada')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No hay órdenes pendientes con radiólogo asignado.');
            return self::SUCCESS;
        }

        // Agrupar por radiólogo
        $grouped = $rows->groupBy('email');
        $dryRun  = $this->option('dry-run');

        foreach ($grouped as $email => $ordenes) {
            $radiologo = $ordenes->first()->radiologo;

            $lista = $ordenes->map(function ($o) {
                $dias = (int) Carbon::parse($o->enviada)->diffInDays(now());
                return [
                    'id'      => $o->id,
                    'paciente'=> $o->paciente,
                    'enviada' => Carbon::parse($o->enviada)->format('d/m/Y H:i'),
                    'dias'    => $dias,
                ];
            })->all();

            if ($dryRun) {
                $this->line("  DRY-RUN → {$email} ({$radiologo}): " . count($lista) . ' orden(es)');
                continue;
            }

            try {
                Mail::to($email)->send(new AlertaRadiologo($radiologo, $lista));
                $this->info("  Enviado → {$email} ({$radiologo}): " . count($lista) . ' orden(es)');
            } catch (\Throwable $e) {
                $this->error("  Error enviando a {$email}: " . $e->getMessage());
            }

            sleep(1); // evitar rate-limiting del servidor de correo
        }

        $this->info('Proceso completado.');
        return self::SUCCESS;
    }
}
