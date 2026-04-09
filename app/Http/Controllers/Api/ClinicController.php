<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicController extends Controller
{
    // GET /api/v3/clinic/by-holding
    public function listByHolding(Request $request)
    {
        $holdingId = $request->_holding_id;

        $clinics = DB::table('clinics as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.holding_id', $holdingId)
            ->select('c.id', 'u.name', 'c.address', 'c.phone1', 'c.phone2', 'c.email')
            ->orderBy('u.name')
            ->get()
            ->map(fn ($c) => [
                'id'      => $c->id,
                'name'    => $c->name,
                'address' => $c->address,
                'phones'  => array_filter([$c->phone1, $c->phone2]),
                'email'   => $c->email,
            ]);

        return response()->json($clinics);
    }
}
