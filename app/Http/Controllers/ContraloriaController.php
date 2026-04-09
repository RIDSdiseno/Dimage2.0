<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ContraloriaController extends Controller
{
    public function index(Request $request)
    {
        $search  = trim($request->get('search', ''));
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 15;

        $query = DB::table('accounts as a')
            ->join('patients as p', 'p.id', '=', 'a.patient_id')
            ->join('clinics as c', 'c.id', '=', 'a.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->leftJoin('staffs as s', 's.id', '=', 'a.staff_id')
            ->leftJoin('users as us', 'us.id', '=', 's.user_id')
            ->select(
                'a.id', 'a.estado', 'a.diagnostico', 'a.created_at',
                'p.name as paciente', 'p.rut',
                'uc.name as clinica', 'us.name as contralor'
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', "%{$search}%")
                  ->orWhere('p.rut', 'like', "%{$search}%")
                  ->orWhere('uc.name', 'like', "%{$search}%")
                  ->orWhere('a.id', 'like', "%{$search}%");
            });
        }

        $total    = $query->count();
        $accounts = $query->orderByDesc('a.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'paciente'     => $a->paciente,
                'rut'          => $a->rut,
                'clinica'      => $a->clinica,
                'contralor'    => $a->contralor ?? '—',
                'estado'       => (int) $a->estado,
                'status_label' => (int) $a->estado === 1 ? 'Respondida' : 'Pendiente',
                'created_at'   => $a->created_at ? Carbon::parse($a->created_at)->format('d/m/Y') : '—',
            ]);

        return Inertia::render('Admin/Controloria/Index', [
            'accounts'    => $accounts,
            'total'       => $total,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'filters'     => ['search' => $search],
        ]);
    }

    public function create()
    {
        $contralores = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.type_staff', 7)
            ->where('s.activo', 1)
            ->select('s.id', 'u.name')
            ->orderBy('u.name')
            ->get()
            ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name]);

        $clinicas = DB::table('clinics as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->select('c.id', 'u.name')
            ->orderBy('u.name')
            ->get()
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);

        return Inertia::render('Admin/Controloria/Form', [
            'account'     => null,
            'contralores' => $contralores,
            'clinicas'    => $clinicas,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id'  => ['required', 'exists:patients,id'],
            'clinic_id'   => ['required', 'exists:clinics,id'],
            'staff_id'    => ['required', 'exists:staffs,id'],
            'diagnostico' => ['required', 'string', 'min:5'],
        ]);

        $account = Account::create([
            'patient_id'   => $request->patient_id,
            'clinic_id'    => $request->clinic_id,
            'staff_id'     => $request->staff_id,
            'diagnostico'  => $request->diagnostico,
            'observaciones'=> $request->observaciones ?? '',
            'detalle'      => $request->detalle ?? '',
            'creador_id'   => Auth::id(),
            'estado'       => 0,
        ]);

        foreach ($request->file('archivos', []) as $file) {
            $path = $file->store("contralorias/{$account->id}", 's3');
            DB::table('archives')->insert([
                'account_id'  => $account->id,
                'name'        => $file->getClientOriginalName(),
                'path'        => $path,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return redirect()->route('admin.controloria')->with('success', 'Contraloría creada correctamente.');
    }

    public function show($id)
    {
        $a = DB::table('accounts as ac')
            ->join('patients as p', 'p.id', '=', 'ac.patient_id')
            ->join('clinics as c', 'c.id', '=', 'ac.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->leftJoin('staffs as s', 's.id', '=', 'ac.staff_id')
            ->leftJoin('users as us', 'us.id', '=', 's.user_id')
            ->where('ac.id', $id)
            ->select(
                'ac.id', 'ac.patient_id', 'ac.clinic_id', 'ac.staff_id',
                'ac.estado', 'ac.diagnostico', 'ac.observaciones', 'ac.detalle', 'ac.respuesta',
                'ac.created_at',
                'p.name as paciente', 'p.rut',
                'uc.name as clinica',
                'us.name as contralor'
            )
            ->first();

        abort_if(! $a, 404);

        $archives = DB::table('archives')->where('account_id', $id)->get(['id', 'name', 'path'])
            ->map(function ($f) {
                $url = null;
                if ($f->path) {
                    try { $url = Storage::disk('s3')->temporaryUrl($f->path, now()->addMinutes(60)); } catch (\Throwable) {}
                }
                return ['id' => $f->id, 'name' => $f->name, 'url' => $url];
            });

        return Inertia::render('Admin/Controloria/Show', [
            'account' => [
                'id'           => $a->id,
                'paciente'     => $a->paciente,
                'rut'          => $a->rut,
                'clinica'      => $a->clinica,
                'contralor'    => $a->contralor ?? '—',
                'estado'       => (int) $a->estado,
                'diagnostico'  => $a->diagnostico,
                'observaciones'=> $a->observaciones,
                'detalle'      => $a->detalle,
                'respuesta'    => $a->respuesta,
                'created_at'   => $a->created_at ? Carbon::parse($a->created_at)->format('d/m/Y H:i') : '—',
                'archives'     => $archives,
            ],
        ]);
    }

    public function addArchive(Request $request, $id): RedirectResponse
    {
        $request->validate(['archivos.*' => ['file', 'max:51200']]);

        abort_if(!DB::table('accounts')->where('id', $id)->exists(), 404);

        foreach ($request->file('archivos', []) as $file) {
            $path = $file->store("contralorias/{$id}", 's3');
            DB::table('archives')->insert([
                'account_id' => $id,
                'name'       => $file->getClientOriginalName(),
                'path'       => $path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Archivo(s) agregado(s) correctamente.');
    }

    public function pdf($id): \Illuminate\Http\Response
    {
        $a = DB::table('accounts as ac')
            ->join('patients as p', 'p.id', '=', 'ac.patient_id')
            ->join('clinics as c', 'c.id', '=', 'ac.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->leftJoin('staffs as s', 's.id', '=', 'ac.staff_id')
            ->leftJoin('users as us', 'us.id', '=', 's.user_id')
            ->where('ac.id', $id)
            ->select(
                'ac.id', 'ac.estado', 'ac.diagnostico', 'ac.observaciones', 'ac.detalle',
                'ac.respuesta', 'ac.created_at',
                'p.name as paciente', 'p.rut',
                'uc.name as clinica',
                'us.name as contralor'
            )
            ->first();

        abort_if(! $a, 404);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.controloria', [
            'account'      => $a,
            'paciente'     => $a->paciente,
            'rut'          => $a->rut,
            'clinica'      => $a->clinica,
            'contralor'    => $a->contralor ?? '—',
            'estado'       => (int) $a->estado,
            'diagnostico'  => $a->diagnostico,
            'observaciones'=> $a->observaciones,
            'detalle'      => $a->detalle,
            'respuesta'    => $a->respuesta,
        ]);

        return $pdf->download("controloria-{$id}.pdf");
    }

    public function destroyArchive($archive): RedirectResponse
    {
        $file = DB::table('archives')->where('id', $archive)->first(['id', 'path', 'account_id']);
        abort_if(!$file, 404);

        if ($file->path) {
            try { Storage::disk('s3')->delete($file->path); } catch (\Throwable) {}
        }

        DB::table('archives')->where('id', $archive)->delete();

        return back()->with('success', 'Archivo eliminado.');
    }

    public function respond(Request $request, $id)
    {
        $request->validate([
            'respuesta' => ['required', 'string', 'min:5'],
        ]);

        Account::where('id', $id)->update([
            'respuesta'  => $request->respuesta,
            'estado'     => 1,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.controloria.show', $id)->with('success', 'Respuesta guardada.');
    }
}
