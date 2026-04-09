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
        $row = $this->query($request->_holding_id)->where('u.rut', $rut)->first();

        if (! $row) {
            return response()->json(['error' => 'Odontólogo no encontrado.'], 404);
        }

        return response()->json($this->format($row));
    }

    // GET /api/v3/odontologo/by-holding
    public function listByHolding(Request $request)
    {
        $rows = $this->query($request->_holding_id)
            ->orderBy('u.name')
            ->get()
            ->map(fn ($r) => $this->format($r));

        return response()->json($rows);
    }

    // POST /api/v3/odontologo/create
    public function create(Request $request)
    {
        $data = $request->validate([
            'rut'      => ['required', 'string', 'unique:users,rut'],
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'clinic_id'=> ['nullable', 'exists:clinics,id'],
        ]);

        $userId = DB::table('users')->insertGetId([
            'rut'        => $data['rut'],
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'type_id'    => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staffId = DB::table('staffs')->insertGetId([
            'user_id'    => $userId,
            'type_staff' => 6,
            'activo'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!empty($data['clinic_id'])) {
            DB::table('clinic_staff')->insert([
                'clinic_id' => $data['clinic_id'],
                'staff_id'  => $staffId,
            ]);
        }

        return response()->json(['message' => 'Odontólogo creado.', 'staff_id' => $staffId], 201);
    }

    private function query(int $holdingId)
    {
        return DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('c.holding_id', $holdingId)
            ->where('s.type_staff', 6)
            ->select('s.id as staff_id', 'u.id as user_id', 'u.name', 'u.email', 'u.rut', 's.activo')
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
