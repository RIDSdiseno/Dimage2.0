<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $users = [];

        if ($user->type_id === 1 || $user->hasRole('admin')) {
            $users = DB::table('users')
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'type_id'])
                ->map(fn($u) => ['value' => $u->id, 'label' => $u->name, 'type_id' => $u->type_id]);
        }

        return Inertia::render('Calendar/Index', [
            'isAdmin' => $user->type_id === 1 || $user->hasRole('admin'),
            'users'   => $users,
        ]);
    }

    public function events(Request $request)
    {
        $user  = Auth::user();
        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);

        $query = DB::table('calendar_events as e')
            ->join('users as u', 'u.id', '=', 'e.created_by')
            ->whereBetween('e.fecha', [
                "{$year}-{$month}-01",
                date('Y-m-t', mktime(0, 0, 0, $month, 1, $year)),
            ]);

        // Admin ve todos los eventos; el resto solo los suyos
        if ($user->type_id !== 1 && ! $user->hasRole('admin')) {
            $query->where('e.user_id', $user->id);
        }

        $events = $query
            ->select('e.id', 'e.title', 'e.description', 'e.fecha',
                     'e.hora_inicio', 'e.color', 'e.user_id', 'e.created_by',
                     'u.name as creado_por')
            ->orderBy('e.fecha')
            ->orderBy('e.hora_inicio')
            ->get()
            ->map(fn($e) => [
                'id'          => $e->id,
                'title'       => $e->title,
                'description' => $e->description,
                'fecha'       => $e->fecha,
                'hora_inicio' => $e->hora_inicio ? substr($e->hora_inicio, 0, 5) : null,
                'color'       => $e->color,
                'user_id'     => $e->user_id,
                'created_by'  => $e->created_by,
                'creado_por'  => $e->creado_por,
                'propio'      => $e->created_by === $user->id,
                'type'        => 'event',
            ]);

        // Feriados del mes — visibles para todos
        $feriados = DB::table('feriados')
            ->whereBetween('fecha', [
                "{$year}-{$month}-01",
                date('Y-m-t', mktime(0, 0, 0, $month, 1, $year)),
            ])
            ->orderBy('fecha')
            ->get()
            ->map(fn($f) => [
                'id'          => 'feriado-' . $f->id,
                'title'       => $f->descripcion,
                'description' => null,
                'fecha'       => $f->fecha,
                'hora_inicio' => null,
                'color'       => '#dc2626',
                'type'        => 'feriado',
            ]);

        return response()->json($events->concat($feriados)->sortBy('fecha')->values());
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'fecha'       => ['required', 'date'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'color'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'user_id'     => ['nullable', 'exists:users,id'],
        ]);

        // Solo admin puede asignar a otro usuario
        $targetUserId = ($user->type_id === 1 || $user->hasRole('admin')) && $request->filled('user_id')
            ? $request->user_id
            : $user->id;

        DB::table('calendar_events')->insert([
            'title'       => $request->title,
            'description' => $request->description,
            'fecha'       => $request->fecha,
            'hora_inicio' => $request->hora_inicio,
            'color'       => $request->color ?? '#3452ff',
            'user_id'     => $targetUserId,
            'created_by'  => $user->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Evento creado.');
    }

    public function update(Request $request, $id)
    {
        $user  = Auth::user();
        $event = DB::table('calendar_events')->where('id', $id)->first();
        abort_if(! $event, 404);

        // Solo el creador o un admin puede editar
        if ($event->created_by !== $user->id && $user->type_id !== 1 && ! $user->hasRole('admin')) {
            abort(403);
        }

        $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'fecha'       => ['required', 'date'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'color'       => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
            'user_id'     => ['nullable', 'exists:users,id'],
        ]);

        $targetUserId = ($user->type_id === 1 || $user->hasRole('admin')) && $request->filled('user_id')
            ? $request->user_id
            : $event->user_id;

        DB::table('calendar_events')->where('id', $id)->update([
            'title'       => $request->title,
            'description' => $request->description,
            'fecha'       => $request->fecha,
            'hora_inicio' => $request->hora_inicio,
            'color'       => $request->color ?? $event->color,
            'user_id'     => $targetUserId,
            'updated_at'  => now(),
        ]);

        return back()->with('success', 'Evento actualizado.');
    }

    public function destroy($id)
    {
        $user  = Auth::user();
        $event = DB::table('calendar_events')->where('id', $id)->first();
        abort_if(! $event, 404);

        if ($event->created_by !== $user->id && $user->type_id !== 1 && ! $user->hasRole('admin')) {
            abort(403);
        }

        DB::table('calendar_events')->where('id', $id)->delete();
        return back()->with('success', 'Evento eliminado.');
    }
}
