<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PatientController extends Controller
{
    // GET /api/v3/patient/{rut}
    public function findByRut(Request $request, string $rut)
    {
        $holdingId = $request->_holding_id;

        $enHolding = DB::table('patients as p')
            ->leftJoin('clinic_patient as cp', 'p.id', '=', 'cp.patient_id')
            ->leftJoin('clinics as c', 'cp.clinic_id', '=', 'c.id')
            ->where('c.holding_id', $holdingId)
            ->where('p.rut', $rut)
            ->exists();

        if (! $enHolding) {
            return response()->json(['error' => "Paciente de rut $rut no existe"], 404);
        }

        $patient = DB::table('patients')->where('rut', $rut)->first();

        return response()->json($this->format($patient));
    }

    // POST /api/v3/patient
    public function create(Request $request)
    {
        $data = $request->validate([
            'rut'           => ['required', 'string'],
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'celphone'      => ['nullable', 'string', 'max:30'],
            'housephone'    => ['nullable', 'string', 'max:30'],
            'address'       => ['nullable', 'string', 'max:255'],
            'dateofbirth'   => ['nullable', 'string', 'max:20'],
            'tutorname'     => ['nullable', 'string', 'max:255'],
            'tutorrelation' => ['nullable', 'string', 'max:100'],
            'id_externo'    => ['nullable', 'string', 'max:100'],
        ]);

        $holdingId = $request->_holding_id;

        // Si el paciente ya existe, actualizarlo y devolverlo — no fallar con 422
        $existing = DB::table('patients')->where('rut', $data['rut'])->first();

        if ($existing) {
            DB::table('patients')->where('rut', $data['rut'])->update([
                'name'       => $data['name'],
                'updated_at' => now(),
            ]);
            $patient = DB::table('patients')->where('rut', $data['rut'])->first();
            $this->syncClinicas($patient->id, $holdingId);
            return response()->json($this->format($patient), 200);
        }

        $id = DB::table('patients')->insertGetId([
            'rut'           => $data['rut'],
            'name'          => $data['name'],
            'email'         => $data['email'] ?? '',
            'celphone'      => $data['celphone'] ?? '',
            'housephone'    => $data['housephone'] ?? '',
            'workphone'     => '',
            'address'       => $data['address'] ?? '',
            'lat'           => '',
            'long'          => '',
            'dateofbirth'   => $data['dateofbirth'] ?? '',
            'tutorname'     => $data['tutorname'] ?? '',
            'tutorrelation' => $data['tutorrelation'] ?? '0',
            'id_externo'    => $data['id_externo'] ?? '0',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Vincular a todas las clínicas del holding (igual que el sistema legacy)
        $this->syncClinicas($id, $holdingId);

        $patient = DB::table('patients')->where('id', $id)->first();

        return response()->json($this->format($patient), 201);
    }

    /**
     * Vincula el paciente a todas las clínicas del holding en clinic_patient.
     * Replica el comportamiento del sistema legacy (clinic()->sync()).
     */
    private function syncClinicas(int $patientId, int $holdingId): void
    {
        $clinicIds = DB::table('clinics')
            ->where('holding_id', $holdingId)
            ->pluck('id');

        foreach ($clinicIds as $clinicId) {
            $exists = DB::table('clinic_patient')
                ->where('clinic_id', $clinicId)
                ->where('patient_id', $patientId)
                ->exists();

            if (! $exists) {
                DB::table('clinic_patient')->insert([
                    'clinic_id'  => $clinicId,
                    'patient_id' => $patientId,
                    'created_at' => now(),
                ]);
            }
        }
    }

    // PUT /api/v3/patient/{rut}
    public function update(Request $request, string $rut)
    {
        $holdingId = $request->_holding_id;

        // Validar que el paciente pertenezca al holding del API key (igual que legacy)
        $patient = DB::table('patients as p')
            ->leftJoin('clinic_patient as cp', 'p.id', '=', 'cp.patient_id')
            ->leftJoin('clinics as c', 'cp.clinic_id', '=', 'c.id')
            ->where('c.holding_id', $holdingId)
            ->where('p.rut', $rut)
            ->select('p.*')
            ->first();

        if (! $patient) {
            return response()->json(['error' => "Paciente de rut $rut no existe"], 404);
        }

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'celphone'      => ['nullable', 'string', 'max:30'],
            'housephone'    => ['nullable', 'string', 'max:30'],
            'address'       => ['nullable', 'string', 'max:255'],
            'dateofbirth'   => ['nullable', 'string', 'max:20'],
            'tutorname'     => ['nullable', 'string', 'max:255'],
            'tutorrelation' => ['nullable', 'string', 'max:100'],
            'id_externo'    => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('patients')->where('rut', $rut)->update(array_merge($data, [
            'updated_at' => now(),
        ]));

        // Sincronizar clínicas del holding al editar (igual que legacy)
        $this->syncClinicas($patient->id, $holdingId);

        return response()->json($this->format(DB::table('patients')->where('rut', $rut)->first()));
    }

    private function format(object $p): array
    {
        return [
            'id'            => $p->id,
            'rut'           => $p->rut,
            'name'          => $p->name,
            'email'         => $p->email,
            'celphone'      => $p->celphone,
            'housephone'    => $p->housephone,
            'address'       => $p->address,
            'dateofbirth'   => $p->dateofbirth,
            'tutorname'     => $p->tutorname,
            'tutorrelation' => $p->tutorrelation,
            'id_externo'    => $p->id_externo,
        ];
    }
}
