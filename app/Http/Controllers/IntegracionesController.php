<?php

namespace App\Http\Controllers;

use App\Models\HoldingApikey;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class IntegracionesController extends Controller
{
    public function index(Request $request)
    {
        $search  = trim($request->get('search', ''));
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 15;

        $query = DB::table('holding_apikey as k')
            ->join('holdings as h', 'h.id', '=', 'k.holding_id')
            ->join('users as u', 'u.id', '=', 'h.user_id')
            ->select('k.id', 'k.descripcion', 'k.activo', 'k.created_at', 'u.name as holding', 'k.holding_id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('k.descripcion', 'like', "%{$search}%")
                  ->orWhere('u.name', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $keys  = $query->orderByDesc('k.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($k) => [
                'id'          => $k->id,
                'descripcion' => $k->descripcion,
                'holding'     => $k->holding,
                'holding_id'  => $k->holding_id,
                'activo'      => (bool) $k->activo,
                'created_at'  => $k->created_at ? Carbon::parse($k->created_at)->format('d/m/Y') : '—',
            ]);

        return Inertia::render('Admin/Integraciones/Index', [
            'apikeys'     => $keys,
            'total'       => $total,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'filters'     => ['search' => $search],
        ]);
    }

    public function create()
    {
        $holdings = DB::table('holdings as h')
            ->join('users as u', 'u.id', '=', 'h.user_id')
            ->select('h.id', 'u.name')
            ->orderBy('u.name')
            ->get()
            ->map(fn ($h) => ['value' => $h->id, 'label' => $h->name]);

        return Inertia::render('Admin/Integraciones/Form', [
            'apikey'   => null,
            'holdings' => $holdings,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'holding_id'  => ['required', 'exists:holdings,id'],
            'descripcion' => ['required', 'string', 'max:255'],
        ]);

        $key = new HoldingApikey();
        $key->holding_id  = $request->holding_id;
        $key->descripcion = $request->descripcion;
        $key->activo      = true;
        $key->apikey      = bin2hex(random_bytes(32));
        $key->save();

        return redirect()->route('admin.integraciones')
            ->with('success', 'API Key creada. Copia la clave ahora — no se volverá a mostrar.')
            ->with('nueva_key', $key->apikey);
    }

    public function edit($id)
    {
        $k = DB::table('holding_apikey')->where('id', $id)->first();
        abort_if(! $k, 404);

        $holdings = DB::table('holdings as h')
            ->join('users as u', 'u.id', '=', 'h.user_id')
            ->select('h.id', 'u.name')
            ->orderBy('u.name')
            ->get()
            ->map(fn ($h) => ['value' => $h->id, 'label' => $h->name]);

        return Inertia::render('Admin/Integraciones/Form', [
            'apikey' => [
                'id'          => $k->id,
                'holding_id'  => $k->holding_id,
                'descripcion' => $k->descripcion,
                'activo'      => (bool) $k->activo,
            ],
            'holdings' => $holdings,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'holding_id'  => ['required', 'exists:holdings,id'],
            'descripcion' => ['required', 'string', 'max:255'],
        ]);

        HoldingApikey::where('id', $id)->update([
            'holding_id'  => $request->holding_id,
            'descripcion' => $request->descripcion,
            'activo'      => (bool) $request->activo,
            'updated_at'  => now(),
        ]);

        return redirect()->route('admin.integraciones')->with('success', 'API Key actualizada.');
    }

    public function destroy($id)
    {
        HoldingApikey::where('id', $id)->update(['activo' => false, 'updated_at' => now()]);
        return redirect()->route('admin.integraciones')->with('success', 'API Key desactivada.');
    }
}
