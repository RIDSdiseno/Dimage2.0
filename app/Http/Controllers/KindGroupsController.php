<?php

namespace App\Http\Controllers;

use App\Models\KindGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KindGroupsController extends Controller
{
    const TABS = [
        'intraorales' => 'Radiografías Intraorales',
        'extraorales' => 'Radiografías Extraorales',
    ];

    public function index(): Response
    {
        $grupos = KindGroup::withCount('kinds')->orderBy('tab')->orderBy('orden')->orderBy('id')->get();

        return Inertia::render('Admin/Examenes/Categorias/Index', [
            'grupos' => $grupos,
            'tabs'   => self::TABS,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Examenes/Categorias/Form', [
            'grupo' => null,
            'tabs'  => self::TABS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tab'    => ['required', 'in:intraorales,extraorales'],
            'orden'  => ['nullable', 'integer', 'min:0'],
        ]);

        KindGroup::create([
            'nombre' => $request->nombre,
            'tab'    => $request->tab,
            'orden'  => $request->input('orden', 0),
        ]);

        return redirect()->route('admin.examenes.categorias')->with('success', 'Categoría creada correctamente.');
    }

    public function edit(int $id): Response
    {
        $grupo = KindGroup::findOrFail($id);

        return Inertia::render('Admin/Examenes/Categorias/Form', [
            'grupo' => $grupo,
            'tabs'  => self::TABS,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tab'    => ['required', 'in:intraorales,extraorales'],
            'orden'  => ['nullable', 'integer', 'min:0'],
        ]);

        KindGroup::where('id', $id)->update([
            'nombre' => $request->nombre,
            'tab'    => $request->tab,
            'orden'  => $request->input('orden', 0),
        ]);

        return redirect()->route('admin.examenes.categorias')->with('success', 'Categoría actualizada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $count = DB::table('kinds')->where('group', $id)->count();
        if ($count > 0) {
            return back()->with('error', "No se puede eliminar: hay {$count} examen(es) en esta categoría.");
        }

        KindGroup::destroy($id);

        return redirect()->route('admin.examenes.categorias')->with('success', 'Categoría eliminada.');
    }
}
