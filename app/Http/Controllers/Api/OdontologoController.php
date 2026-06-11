<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OdontologoController extends Controller
{
    // GET /api/v3/odontologo/by-rut/{rut}
    public function findByRut(Request $request, string $rut)
    {
        \Log::info('ODONTOLOGO_BY_RUT', ['rut' => $rut, 'all' => $request->all()]);

        $rutInput = strtoupper(preg_replace('/[^0-9K]/', '', $rut));

$row = $this->query($request->_holding_id)
    ->where(function ($q) use ($rutInput) {
        $rutDb = "REPLACE(REPLACE(UPPER(s.rut), '.', ''), '-', '')";

        $q->whereRaw("$rutDb = ?", [$rutInput])
          ->orWhereRaw("LEFT($rutDb, CHAR_LENGTH($rutDb) - 1) = ?", [$rutInput]);
    })
    ->first();

        if (! $row) {
            return response()->json(['error' => 'Odontólogo no encontrado.'], 404);
        }

        return response()->json($this->format($row));
    }

    // GET /api/v3/odontologo/by-holding
    public function listByHolding(Request $request)
    {
        \Log::info('ODONTOLOGO_BY_HOLDING', ['holding_id' => $request->_holding_id]);

        $rows = $this->query($request->_holding_id)
            ->orderBy('u.name')
            ->get()
            ->unique('rut')
            ->values()
            ->map(fn ($r) => $this->format($r));

        return response()->json($rows);
    }

    // POST /api/v3/odontologo/create
    public function create(Request $request)
    {
        \Log::info('ODONTOLOGO_CREATE', ['all' => $request->all()]);

        $rutRaw = $request->input('rut') ?? $request->input('odontologo') ?? '';
        $rutInput = strtoupper(preg_replace('/[^0-9K]/', '', $rutRaw));

        if ($rutInput === '') {
            return response()->json([
                'error' => 'RUT de odontólogo requerido.',
                'received' => $request->all(),
            ], 422);
        }

        $name = $request->input('name')
            ?? $request->input('nombre')
            ?? $request->input('odontologo_nombre')
            ?? 'Odontólogo ' . $rutInput;

        $email = $request->input('email')
            ?? $request->input('mail')
            ?? $request->input('correo')
            ?? ('odontologo_' . $rutInput . '@dimage.local');

        $password = $request->input('password') ?? '123456';

        $idExterno = $request->input('id_externo')
            ?? $request->input('profesional_id')
            ?? $request->input('odontologo_id_externo')
            ?? null;

        // DentalSoft incrusta su ID interno en el username: "{ds_id}odo{rut_digits}"
        if (!$idExterno && $request->filled('username')) {
            if (preg_match('/^(\d+)odo\d+$/', $request->input('username'), $m)) {
                $idExterno = $m[1];
            }
        }

        // Búsqueda global por RUT (sin filtrar por holding/clinic_staff)
        // para evitar duplicados cuando el staff existe pero no tiene vínculo a esa clínica.
        $rutDb = "REPLACE(REPLACE(UPPER(s.rut), '.', ''), '-', '')";

        $existing = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.type_staff', 6)
            ->where(function ($q) use ($rutInput, $rutDb) {
                $q->whereRaw("{$rutDb} = ?", [$rutInput])
                  ->orWhereRaw("LEFT({$rutDb}, CHAR_LENGTH({$rutDb}) - 1) = ?", [$rutInput]);
            })
            ->select('s.id as staff_id', 'u.id as user_id', 'u.name', 'u.email', 's.rut', 's.activo')
            ->orderBy('s.id')
            ->first();

        if ($existing) {
            // Actualizar id_externo si se provee y aún no está guardado
            if ($idExterno) {
                $currentIdExterno = DB::table('staffs')->where('id', $existing->staff_id)->value('id_externo');
                if (!$currentIdExterno) {
                    DB::table('staffs')->where('id', $existing->staff_id)->update(['id_externo' => $idExterno]);
                }
            }
            if ($request->filled('clinic_id')) {
                DB::table('clinic_staff')->insertOrIgnore([
                    'clinic_id' => $request->input('clinic_id'),
                    'staff_id'  => $existing->staff_id,
                ]);
            }
            // Retorna 201 (igual que al crear) para que DentalSoft no distinga entre nuevo y existente
            return response()->json($this->format($existing), 201);
        }

        $userId = DB::table('users')->insertGetId([
            'username'   => $email,
            'password'   => Hash::make($password),
            'name'       => $name,
            'mail'       => $email,
            'email'      => $email,
            'status'     => 1,
            'type_id'    => 6,
            'id_externo' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staffId = DB::table('staffs')->insertGetId([
            'user_id'    => $userId,
            'rut'        => $rutInput,
            'type_staff' => 6,
            'activo'     => 1,
            'id_externo' => $idExterno,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($request->filled('clinic_id')) {
            DB::table('clinic_staff')->insertOrIgnore([
                'clinic_id' => $request->input('clinic_id'),
                'staff_id'  => $staffId,
            ]);
        }

        $row = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $staffId)
            ->select('s.id as staff_id', 'u.id as user_id', 'u.name', 'u.email', 's.rut', 's.activo')
            ->first();

        return response()->json($this->format($row), 201);
    }


    private function query(int $holdingId)
    {
        return DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('c.holding_id', $holdingId)
            ->where('s.type_staff', 6)
            ->select('s.id as staff_id', 'u.id as user_id', 'u.name', 'u.email', 's.rut', 's.activo')
            ->distinct();
    }

    private function format(object $r): array
    {
        return [
            'staff_id' => $r->staff_id,
            'user_id'  => $r->user_id,
            'name'     => $r->name,
            'email'    => $r->email,
            'rut'      => $r->rut,
            'activo'   => (bool) $r->activo,
        ];
    }
}
