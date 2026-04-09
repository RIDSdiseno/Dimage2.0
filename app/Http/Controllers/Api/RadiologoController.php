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
        $row = $this->baseQuery()->where('u.rut', $rut)->first();

        if (! $row) {
            return response()->json(['error' => 'Radiólogo no encontrado.'], 404);
        }

        return response()->json($this->format($row));
    }

    // GET /api/v3/radiologo/by-holding
    public function findByHolding(Request $request)
    {
        $holdingId = $request->_holding_id;

        $rows = $this->baseQuery()
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('c.holding_id', $holdingId)
            ->orderBy('u.name')
            ->get()
            ->map(fn ($r) => $this->format($r));

        return response()->json($rows);
    }

    // POST /api/v3/radiologo
    public function create(Request $request)
    {
        $data = $request->validate([
            'rut'      => ['required', 'string', 'unique:users,rut'],
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $userId = DB::table('users')->insertGetId([
            'rut'        => $data['rut'],
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'type_id'    => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staffId = DB::table('staffs')->insertGetId([
            'user_id'    => $userId,
            'type_staff' => 3,
            'activo'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Radiólogo creado.', 'staff_id' => $staffId], 201);
    }

    // PUT /api/v3/radiologo/{rut}
    public function update(Request $request, string $rut)
    {
        $user = DB::table('users')->where('rut', $rut)->first();
        if (! $user) return response()->json(['error' => 'No encontrado.'], 404);

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
        ]);

        DB::table('users')->where('rut', $rut)->update(array_merge($data, ['updated_at' => now()]));

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
            ->where('u.rut', $rut)
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
        $user = DB::table('users')->where('rut', $rut)->first();
        if (! $user) return response()->json(['error' => 'No encontrado.'], 404);

        DB::table('staffs')->where('user_id', $user->id)->update(['activo' => 0, 'updated_at' => now()]);

        return response()->json(['message' => 'Radiólogo desactivado.']);
    }

    private function baseQuery()
    {
        return DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.type_staff', 3)
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
