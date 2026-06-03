<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? (function () use ($request) {
                    $u     = $request->user();
                    try {
                        $staff = Cache::remember("user_staff_{$u->id}", 300, fn () =>
                            DB::table('staffs')->where('user_id', $u->id)->first([
                                'puede_ver_menu_busqueda',
                                'puede_sin_diagnostico',
                                'puede_derivacion_clinica',
                            ])
                        );
                    } catch (\Throwable) {
                        $staff = DB::table('staffs')->where('user_id', $u->id)->first([
                            'puede_ver_menu_busqueda',
                            'puede_sin_diagnostico',
                            'puede_derivacion_clinica',
                        ]);
                    }
                    return [
                        'id'                      => $u->id,
                        'name'                    => $u->name,
                        'email'                   => $u->email,
                        'type_id'                 => $u->type_id,
                        'roles'                   => $u->getRoleNames(),
                        'puede_ver_busqueda'      => (bool) ($staff->puede_ver_menu_busqueda   ?? false),
                        'puede_sin_diagnostico'   => (bool) ($staff->puede_sin_diagnostico     ?? false),
                        'puede_derivacion_clinica'=> (bool) ($staff->puede_derivacion_clinica  ?? false),
                    ];
                })() : null,
            ],
            'region' => $request->session()->get('region', 'CL'),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success'               => $request->session()->get('success'),
                'error'                 => $request->session()->get('error'),
                'nueva_key'             => $request->session()->get('nueva_key'),
                'nuevo_paciente_id'     => $request->session()->get('nuevo_paciente_id'),
                'nuevo_paciente_nombre' => $request->session()->get('nuevo_paciente_nombre'),
            ],
        ];
    }
}
