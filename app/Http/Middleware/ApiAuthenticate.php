<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY');

        if (! $apiKey) {
            return response()->json(['error' => 'API key requerida.'], 401);
        }

        $record = DB::table('holding_apikey')
            ->where('apikey', $apiKey)
            ->where('activo', true)
            ->first(['id', 'holding_id']);

        if (! $record) {
            return response()->json(['error' => 'API key inválida o inactiva.'], 401);
        }

        $request->merge([
            '_holding_id'     => $record->holding_id,
            '_holding_key_id' => $record->id,
        ]);

        return $next($request);
    }
}
