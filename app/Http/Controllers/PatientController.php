<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PatientController extends Controller
{
    public function index()
    {
        return Inertia::render('Patients/Index');
    }

    public function search(Request $request)
    {
        $term = $request->get('q', '');
        $user = Auth::user();

        $query = Patient::query()->with('clinics');

        if ($term) {
            $query->search($term);
        }

        // Filtro por rol
        if ($user->hasRole('holding')) {
            $query->whereHas('clinics', fn($q) =>
                $q->where('holding_id', $user->holding->id)
            );
        } elseif ($user->hasRole('clinica')) {
            $query->whereHas('clinics', fn($q) =>
                $q->where('id', $user->clinic->id)
            );
        } elseif ($user->hasAnyRole(['radiologo', 'contralor'])) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $patients = $query->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data'  => $patients->items(),
            'total' => $patients->total(),
            'pages' => $patients->lastPage(),
        ]);
    }

    public function create()
    {
        $clinics = $this->clinicsForUser();
        return Inertia::render('Patients/Create', ['clinics' => $clinics]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => ['required', 'min:3'],
            'email'       => ['required', 'email'],
            'dateofbirth' => ['required', 'date'],
            'rut'         => ['required'],
            'clinics'     => ['required', 'array', 'min:1'],
        ]);

        // Validar RUT duplicado dentro de los holdings de las clínicas
        $holdings = Clinic::whereIn('id', $request->clinics)
            ->pluck('holding_id');

        $rutExiste = Patient::whereHas('clinics', fn($q) =>
            $q->whereIn('holding_id', $holdings)
        )->where('rut', strtolower(trim($request->rut)))->exists();

        if ($rutExiste) {
            return back()->withErrors(['rut' => 'El RUT ya está registrado en esta red.']);
        }

        DB::transaction(function () use ($request) {
            $patient = Patient::create([
                'name'        => trim($request->name),
                'email'       => trim($request->email),
                'rut'         => strtolower(trim($request->rut)),
                'dateofbirth' => $request->dateofbirth,
                'derivado_de' => $request->derivado_de ?? '',
                'housephone'  => '000000000',
                'celphone'    => '000000000',
                'workphone'   => '000000000',
                'address'     => '000000000',
                'lat'         => $request->lat ?? '',
                'long'        => $request->long ?? '',
            ]);

            $patient->clinics()->sync($request->clinics);
        });

        return redirect()->route('pacientes.index')
            ->with('success', '¡Paciente creado con éxito!');
    }

    public function edit(Patient $patient)
    {
        $clinics        = $this->clinicsForUser();
        $selectedClinics = $patient->clinics->pluck('id');

        return Inertia::render('Patients/Edit', [
            'patient'         => $patient,
            'clinics'         => $clinics,
            'selectedClinics' => $selectedClinics,
        ]);
    }

    public function update(Request $request, Patient $patient)
    {
        $request->validate([
            'name'        => ['required', 'min:3'],
            'email'       => ['required', 'email'],
            'dateofbirth' => ['required', 'date'],
            'clinics'     => ['required', 'array', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $patient) {
            $patient->update([
                'name'        => trim($request->name),
                'email'       => trim($request->email),
                'dateofbirth' => $request->dateofbirth,
                'derivado_de' => $request->derivado_de ?? $patient->derivado_de,
            ]);

            $patient->clinics()->sync($request->clinics);
        });

        return redirect()->route('pacientes.edit', $patient->id)
            ->with('success', '¡Paciente actualizado con éxito!');
    }

    // Devuelve clínicas según rol del usuario autenticado
    private function clinicsForUser(): array
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['admin', 'secretaria'])) {
            $clinics = Clinic::with('user')->get();
        } elseif ($user->hasRole('holding')) {
            $clinics = Clinic::with('user')
                ->where('holding_id', $user->holding->id)->get();
        } elseif ($user->hasRole('clinica')) {
            $clinics = Clinic::with('user')
                ->where('holding_id', $user->clinic->holding_id)->get();
        } else {
            $clinics = collect();
        }

        return $clinics->map(fn($c) => [
            'id'   => $c->id,
            'name' => $c->user->name ?? "Clínica #{$c->id}",
        ])->values()->toArray();
    }
}
