<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Verifica que el tipo de usuario esté entre los permitidos.
     * Uso en rutas: ->middleware('role:1,2')
     */
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = array_map('intval', $types);

        if (! in_array((int) $user->type_id, $allowed, true)) {
            return redirect()->route('dashboard')
                ->with('error', 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
