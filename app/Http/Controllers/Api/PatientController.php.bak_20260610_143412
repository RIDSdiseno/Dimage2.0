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
        $patient = DB::table('patients')->where('rut', $rut)->first();

        if (! $patient) {
            return response()->json(['error' => 'Paciente no encontrado.'], 404);
        }

        return response()->json($this->format($patient));
    }

    // POST /api/v3/patient
    public function create(Request $request)
    {
        $data = $request->validate([
            'rut'           => ['required', 'string', 'unique:patients,rut'],
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'celphone'      => ['nullable', 'string', 'max:30'],
            'housephone'    => ['nullable', 'string', 'max:30'],
            'address'       => ['nullable', 'string', 'max:255'],
            'dateofbirth'   => ['nullable', 'date'],
            'tutorname'     => ['nullable', 'string', 'max:255'],
            'tutorrelation' => ['nullable', 'string', 'max:100'],
            'id_externo'    => ['nullable', 'string', 'max:100'],
        ]);

        $id = DB::table('patients')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $patient = DB::table('patients')->where('id', $id)->first();

        return response()->json($this->format($patient), 201);
    }

    // PUT /api/v3/patient/{rut}
    public function update(Request $request, string $rut)
    {
        $patient = DB::table('patients')->where('rut', $rut)->first();

        if (! $patient) {
            return response()->json(['error' => 'Paciente no encontrado.'], 404);
        }

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'celphone'      => ['nullable', 'string', 'max:30'],
            'housephone'    => ['nullable', 'string', 'max:30'],
            'address'       => ['nullable', 'string', 'max:255'],
            'dateofbirth'   => ['nullable', 'date'],
            'tutorname'     => ['nullable', 'string', 'max:255'],
            'tutorrelation' => ['nullable', 'string', 'max:100'],
            'id_externo'    => ['nullable', 'string', 'max:100'],
        ]);

        DB::table('patients')->where('rut', $rut)->update(array_merge($data, [
            'updated_at' => now(),
        ]));

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
