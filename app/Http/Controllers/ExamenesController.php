<?php

namespace App\Http\Controllers;

use App\Models\Kind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamenesController extends Controller
{
    const GRUPOS = [
        1 => 'Adulto',
        2 => 'Niño',
        3 => 'General',
        4 => '3D',
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Examenes/Index', [
            'grupos' => self::GRUPOS,
        ]);
    }

    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $search  = trim($request->get('search', ''));
        $grupo   = $request->get('grupo', '');
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 15;

        $query = Kind::query()->orderBy('group')->orderBy('id');

        if ($search !== '') {
            $query->where('descipcion', 'like', "%{$search}%");
        }

        if ($grupo !== '' && $grupo !== null) {
            $query->where('group', (int) $grupo);
        }

        $kinds = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data'         => $kinds->items(),
            'total'        => $kinds->total(),
            'current_page' => $kinds->currentPage(),
            'per_page'     => $kinds->perPage(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Examenes/Form', [
            'examen' => null,
            'grupos' => self::GRUPOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'descipcion' => ['required', 'string', 'max:255'],
            'group'      => ['required', 'in:1,2,3,4'],
        ]);

        Kind::create([
            'descipcion' => $request->descipcion,
            'group'      => (int) $request->group,
        ]);

        return redirect()->route('admin.examenes')->with('success', 'Tipo de examen creado correctamente.');
    }

    public function edit(int $id): Response
    {
        $examen = Kind::findOrFail($id);

        return Inertia::render('Admin/Examenes/Form', [
            'examen' => $examen,
            'grupos' => self::GRUPOS,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'descipcion' => ['required', 'string', 'max:255'],
            'group'      => ['required', 'in:1,2,3,4'],
        ]);

        Kind::where('id', $id)->update([
            'descipcion' => $request->descipcion,
            'group'      => (int) $request->group,
        ]);

        return redirect()->route('admin.examenes')->with('success', 'Tipo de examen actualizado.');
    }
}
