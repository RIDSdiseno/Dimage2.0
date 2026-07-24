<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RadiologoController extends Controller
{
    // GET /api/v3/radiologo/by-rut/{rut}
    public function findByRut(Request $request, string $rut)
    {
        $holdingId = $request->_holding_id;

        $row = $this->baseQuery()
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('c.holding_id', $holdingId)
            ->where('s.rut', $rut)
            ->first();

        if (! $row) {
            return response()->json(['error' => 'Radiólogo no encontrado.'], 404);
        }

        return response()->json($this->format($row, (int) $holdingId));
    }

    // GET /api/v3/radiologo/by-holding
    public function findByHolding(Request $request)
    {
        $holdingId = (int) $request->_holding_id;

        $rows = $this->baseQuery()
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('c.holding_id', $holdingId)
            ->orderBy('u.name')
            ->get()
            ->map(fn ($r) => $this->format($r, $holdingId));

        return response()->json($rows);
    }

    // POST /api/v3/radiologo
    public function create(Request $request)
    {
        $holdingId = $request->_holding_id;

        $data = $request->validate([
            'rut'      => ['required', 'string', 'unique:staffs,rut'],
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,mail'],
            'password' => ['required', 'string', 'min:6'],
            'address'  => ['nullable', 'string', 'max:255'],
            'grupos_examenes' => ['nullable', 'array'],
        ]);

        $userId = DB::table('users')->insertGetId([
            'name'       => $data['name'],
            'mail'       => $data['email'],
            'password'   => Hash::make($data['password']),
            'type_id'    => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staffId = DB::table('staffs')->insertGetId([
            'user_id'    => $userId,
            'rut'        => $data['rut'],
            'address'    => $data['address'] ?? '',
            'type_staff' => 3,
            'activo'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Asignar a todas las clínicas del holding (igual que legacy)
        $clinicIds = DB::table('clinics')->where('holding_id', $holdingId)->pluck('id');
        foreach ($clinicIds as $clinicId) {
            DB::table('clinic_staff')->insertOrIgnore([
                'clinic_id' => $clinicId,
                'staff_id'  => $staffId,
            ]);
        }

        return response()->json(['message' => 'Radiólogo creado.', 'staff_id' => $staffId], 201);
    }

    // PUT /api/v3/radiologo/{rut}
    public function update(Request $request, string $rut)
    {
        $holdingId = $request->_holding_id;

        $staff = DB::table('staffs as s')
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('s.rut', $rut)
            ->where('s.type_staff', 3)
            ->where('c.holding_id', $holdingId)
            ->select('s.id', 's.user_id')
            ->first();

        if (! $staff) return response()->json(['error' => 'Radiólogo no encontrado en la red.'], 404);

        $data = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'max:255'],
            'password' => ['sometimes', 'string', 'min:6'],
            'address'  => ['nullable', 'string', 'max:255'],
        ]);

        $userUpdate = [];
        if (isset($data['name']))     $userUpdate['name'] = $data['name'];
        if (isset($data['email']))    $userUpdate['mail'] = $data['email'];
        if (isset($data['password'])) $userUpdate['password'] = Hash::make($data['password']);
        $userUpdate['status'] = 1;

        $userUpdate['updated_at'] = now();
        DB::table('users')->where('id', $staff->user_id)->update($userUpdate);

        if (isset($data['address'])) {
            DB::table('staffs')->where('id', $staff->id)->update(['address' => $data['address'], 'updated_at' => now()]);
        }

        // Re-sincronizar clínicas del holding (igual que legacy)
        $clinicIds = DB::table('clinics')->where('holding_id', $holdingId)->pluck('id');
        foreach ($clinicIds as $clinicId) {
            DB::table('clinic_staff')->insertOrIgnore([
                'clinic_id' => $clinicId,
                'staff_id'  => $staff->id,
            ]);
        }

        return response()->json(['message' => 'Radiólogo actualizado.']);
    }

    // POST /api/v3/radiologo/firma/{rut}
    public function setFirma(Request $request, string $rut)
    {
        $holdingId = $request->_holding_id;

        $radiologo = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('s.rut', $rut)
            ->where('s.type_staff', 3)
            ->where('c.holding_id', $holdingId)
            ->select('s.id as staff_id', 'u.id as user_id', 'u.name')
            ->first();

        if (! $radiologo) {
            return response()->json(['error' => "Radiólogo de rut $rut no existe en la red."], 404);
        }

        $request->validate(['firma' => ['required', 'file', 'image', 'max:4096']]);

        $file = $request->file('firma');
        $path = $file->storeAs(
            'firmas/radiologo',
            md5($radiologo->user_id . $radiologo->name) . '.' . $file->getClientOriginalExtension(),
            's3'
        );

        // Si había firma anterior, la eliminamos
        $oldPhoto = DB::table('users')->where('id', $radiologo->user_id)->value('photo');
        if ($oldPhoto && Storage::disk('s3')->exists($oldPhoto)) {
            Storage::disk('s3')->delete($oldPhoto);
        }

        DB::table('users')->where('id', $radiologo->user_id)->update([
            'photo'      => $path,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Firma actualizada.',
            'url'     => Storage::disk('s3')->temporaryUrl($path, now()->addHours(1)),
        ]);
    }

    // DELETE /api/v3/radiologo/{rut}
    public function destroy(Request $request, string $rut)
    {
        $holdingId = $request->_holding_id;

        $staff = DB::table('staffs as s')
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('s.rut', $rut)
            ->where('s.type_staff', 3)
            ->where('c.holding_id', $holdingId)
            ->select('s.id', 's.user_id')
            ->first();

        if (! $staff) return response()->json(['error' => 'Radiólogo no encontrado en la red.'], 404);

        // Legacy marcaba el user como inactivo (status=0). Mantenemos ese comportamiento.
        DB::table('users')->where('id', $staff->user_id)->update(['status' => 0, 'updated_at' => now()]);
        DB::table('staffs')->where('id', $staff->id)->update(['activo' => 0, 'updated_at' => now()]);

        return response()->json(['message' => 'Radiólogo desactivado.']);
    }

    private function baseQuery()
    {
        return DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.type_staff', 3)
            ->where('s.activo', 1)
            ->select('s.id as staff_id', 'u.id as user_id', 'u.name', 'u.mail as email', 's.rut', 's.activo', 'u.status', 'u.photo')
            ->distinct();
    }

    private function format(object $r, ?int $holdingId = null): array
    {
        // Clínicas del holding donde está asignado (igual que sistema antiguo)
        $clinicasQuery = DB::table('clinic_staff as cs')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->where('cs.staff_id', $r->staff_id);

        if ($holdingId) {
            $clinicasQuery->where('c.holding_id', $holdingId);
        }

        $clinicas = $clinicasQuery->select('c.id', 'uc.name')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->all();

        // Grupos de exámenes asignados (kind_staff)
        $grupos = DB::table('kind_staff')
            ->where('staff_id', $r->staff_id)
            ->pluck('kind_id')
            ->map(fn ($kid) => ['id' => $kid])
            ->values()
            ->all();

        // URL de firma (foto del usuario)
        $firma = null;
        if (! empty($r->photo)) {
            try {
                $firma = Storage::disk('s3')->temporaryUrl($r->photo, now()->addHours(4));
            } catch (\Exception $e) {
                $firma = null;
            }
        }

        return [
            // Campos compatibles con sistema antiguo
            'id'       => $r->staff_id,
            'name'     => $r->name,
            'rut'      => $r->rut,
            'status'   => (int) $r->status,
            'firma'    => $firma,
            'clinicas' => $clinicas,
            'grupos'   => $grupos,
            // Campos adicionales del sistema nuevo (no rompen compatibilidad)
            'staff_id' => $r->staff_id,
            'user_id'  => $r->user_id,
            'email'    => $r->email,
            'activo'   => (bool) $r->activo,
        ];
    }
}
