<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AdminController extends Controller
{
    // ─── Helpers ────────────────────────────────────────────────────────────

    private function requireAdmin(): void
    {
        if (! auth()->user()->hasRole('admin')) {
            abort(403);
        }
    }

    private function typeLabels(): array
    {
        return [
            1  => 'Administrador',
            2  => 'Secretaria',
            3  => 'Holding',
            4  => 'Clínica',
            5  => 'Radiólogo',
            6  => 'Odontólogo',
            7  => 'Contralor',
            11 => 'Técnico',
        ];
    }

    // ─── Administradores ────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->requireAdmin();

        $search  = trim($request->get('search', ''));
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 15;

        $query = DB::table('users')->where('type_id', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('mail', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $admins = $query
            ->select('id', 'name', 'username', 'mail', 'telephone', 'status')
            ->orderBy('name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'username'  => $u->username,
                'mail'      => $u->mail,
                'telephone' => $u->telephone,
                'status'    => (int) $u->status,
            ]);

        return Inertia::render('Admin/Index', [
            'admins'      => $admins,
            'total'       => $total,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'filters'     => ['search' => $search],
        ]);
    }

    public function adminCreate()
    {
        $this->requireAdmin();
        return Inertia::render('Admin/Form', ['admin' => null]);
    }

    public function adminStore(Request $request)
    {
        $this->requireAdmin();
        $request->validate([
            'name'     => ['required', 'min:2'],
            'username' => ['required', 'unique:users,username'],
            'password' => ['required', 'min:6'],
        ]);

        DB::table('users')->insert([
            'name'       => trim($request->name),
            'username'   => trim($request->username),
            'mail'       => trim($request->mail ?? ''),
            'password'   => Hash::make($request->password),
            'type_id'    => 1,
            'telephone'  => trim($request->telephone ?? ''),
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.index')->with('success', 'Administrador creado con éxito.');
    }

    public function adminEdit($id)
    {
        $this->requireAdmin();
        $u = DB::table('users')->where('id', $id)->where('type_id', 1)->first();
        abort_if(! $u, 404);

        return Inertia::render('Admin/Form', [
            'admin' => [
                'id'        => $u->id,
                'name'      => $u->name,
                'username'  => $u->username,
                'mail'      => $u->mail,
                'telephone' => $u->telephone,
                'status'    => (int) $u->status,
            ],
        ]);
    }

    public function adminUpdate(Request $request, $id)
    {
        $this->requireAdmin();
        $u = DB::table('users')->where('id', $id)->where('type_id', 1)->first();
        abort_if(! $u, 404);

        $request->validate([
            'name'     => ['required', 'min:2'],
            'username' => ['required', "unique:users,username,{$id}"],
        ]);

        $data = [
            'name'       => trim($request->name),
            'username'   => trim($request->username),
            'mail'       => trim($request->mail ?? ''),
            'telephone'  => trim($request->telephone ?? ''),
            'updated_at' => now(),
        ];

        if (! empty($request->password)) {
            $request->validate(['password' => ['min:6']]);
            $data['password'] = Hash::make($request->password);
        }

        DB::table('users')->where('id', $id)->update($data);
        return redirect()->route('admin.index')->with('success', 'Administrador actualizado con éxito.');
    }

    public function adminToggle($id)
    {
        $this->requireAdmin();
        $u = DB::table('users')->where('id', $id)->where('type_id', 1)->first();
        abort_if(! $u, 404);

        $new = $u->status ? 0 : 1;
        DB::table('users')->where('id', $id)->update(['status' => $new, 'updated_at' => now()]);
        return back()->with('success', $new ? 'Administrador activado.' : 'Administrador desactivado.');
    }

    // ─── Usuarios ───────────────────────────────────────────────────────────

    public function usuarios(Request $request)
    {
        $this->requireAdmin();

        $search  = trim($request->get('search', ''));
        $typeId  = $request->get('type_id', '');
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 15;

        $query = DB::table('users');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('mail', 'like', "%{$search}%");
            });
        }

        if ($typeId !== '' && $typeId !== null) {
            $query->where('type_id', (int) $typeId);
        }

        $total = $query->count();
        $users = $query
            ->select('id', 'name', 'username', 'mail', 'type_id', 'telephone', 'status')
            ->orderBy('name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $labels = $this->typeLabels();
        $users  = $users->map(fn ($u) => [
            'id'        => $u->id,
            'name'      => $u->name,
            'username'  => $u->username,
            'mail'      => $u->mail,
            'type_id'   => $u->type_id,
            'type_label'=> $labels[$u->type_id] ?? 'Sin tipo',
            'telephone' => $u->telephone,
            'status'    => (int) $u->status,
        ]);

        $tipos = collect($this->typeLabels())->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
        ])->values();

        return Inertia::render('Admin/Usuarios/Index', [
            'usuarios'    => $users,
            'total'       => $total,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'filters'     => ['search' => $search, 'type_id' => $typeId],
            'tipos'       => $tipos,
        ]);
    }

    public function usuariosCreate()
    {
        $this->requireAdmin();

        $tipos = collect($this->typeLabels())->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
        ])->values();

        return Inertia::render('Admin/Usuarios/Form', [
            'user'  => null,
            'tipos' => $tipos,
        ]);
    }

    public function usuariosStore(Request $request)
    {
        $this->requireAdmin();

        $request->validate([
            'name'      => ['required', 'min:2'],
            'username'  => ['required', 'unique:users,username'],
            'type_id'   => ['required', 'integer'],
            'password'  => ['required', 'min:6'],
        ]);

        DB::table('users')->insert([
            'name'       => trim($request->name),
            'username'   => trim($request->username),
            'mail'       => trim($request->mail ?? ''),
            'password'   => Hash::make($request->password),
            'type_id'    => (int) $request->type_id,
            'telephone'  => trim($request->telephone ?? ''),
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.usuarios')
            ->with('success', 'Usuario creado con éxito.');
    }

    public function usuariosEdit(Request $request, $user)
    {
        $this->requireAdmin();

        $u = DB::table('users')->where('id', $user)->first();
        if (! $u) {
            abort(404);
        }

        $tipos = collect($this->typeLabels())->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
        ])->values();

        return Inertia::render('Admin/Usuarios/Form', [
            'user'  => [
                'id'        => $u->id,
                'name'      => $u->name,
                'username'  => $u->username,
                'mail'      => $u->mail,
                'type_id'   => $u->type_id,
                'telephone' => $u->telephone,
                'status'    => (int) $u->status,
            ],
            'tipos' => $tipos,
        ]);
    }

    public function usuariosUpdate(Request $request, $user)
    {
        $this->requireAdmin();

        $u = DB::table('users')->where('id', $user)->first();
        if (! $u) {
            abort(404);
        }

        $request->validate([
            'name'     => ['required', 'min:2'],
            'username' => ['required', "unique:users,username,{$user}"],
            'type_id'  => ['required', 'integer'],
        ]);

        $data = [
            'name'       => trim($request->name),
            'username'   => trim($request->username),
            'mail'       => trim($request->mail ?? ''),
            'type_id'    => (int) $request->type_id,
            'telephone'  => trim($request->telephone ?? ''),
            'updated_at' => now(),
        ];

        if (! empty($request->password)) {
            $request->validate(['password' => ['min:6']]);
            $data['password'] = Hash::make($request->password);
        }

        DB::table('users')->where('id', $user)->update($data);

        return redirect()->route('admin.usuarios')
            ->with('success', 'Usuario actualizado con éxito.');
    }

    public function usuariosToggle(Request $request, $user)
    {
        $this->requireAdmin();

        $u = DB::table('users')->where('id', $user)->first();
        if (! $u) {
            abort(404);
        }

        $newStatus = $u->status ? 0 : 1;
        DB::table('users')->where('id', $user)->update([
            'status'     => $newStatus,
            'updated_at' => now(),
        ]);

        return back()->with('success', $newStatus ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    // ─── Holdings ───────────────────────────────────────────────────────────

    public function holdings(Request $request)
    {
        $this->requireAdmin();

        $search  = trim($request->get('search', ''));
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 15;

        $query = DB::table('holdings')
            ->join('users', 'users.id', '=', 'holdings.user_id')
            ->select(
                'holdings.id',
                'users.name',
                'holdings.rutholding',
                'holdings.emailresponsable',
                'holdings.telefonoresponsable',
                'holdings.representantelegal'
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                  ->orWhere('holdings.rutholding', 'like', "%{$search}%")
                  ->orWhere('holdings.emailresponsable', 'like', "%{$search}%");
            });
        }

        $total    = $query->count();
        $holdings = $query->orderBy('users.name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Attach clinic counts
        $ids    = $holdings->pluck('id');
        $counts = DB::table('clinics')
            ->whereIn('holding_id', $ids)
            ->selectRaw('holding_id, count(*) as cnt')
            ->groupBy('holding_id')
            ->pluck('cnt', 'holding_id');

        $holdings = $holdings->map(fn ($h) => [
            'id'                  => $h->id,
            'name'                => $h->name,
            'rutholding'          => $h->rutholding,
            'emailresponsable'    => $h->emailresponsable,
            'telefonoresponsable' => $h->telefonoresponsable,
            'representantelegal'  => $h->representantelegal,
            'clinicas_count'      => $counts[$h->id] ?? 0,
        ]);

        return Inertia::render('Admin/Holdings/Index', [
            'holdings'    => $holdings,
            'total'       => $total,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'filters'     => ['search' => $search],
        ]);
    }

    public function holdingsCreate()
    {
        $this->requireAdmin();

        return Inertia::render('Admin/Holdings/Form', ['holding' => null]);
    }

    public function holdingsStore(Request $request)
    {
        $this->requireAdmin();

        $request->validate([
            'name'     => ['required', 'min:2'],
            'username' => ['required', 'unique:users,username'],
        ]);

        DB::transaction(function () use ($request) {
            $userId = DB::table('users')->insertGetId([
                'name'       => trim($request->name),
                'username'   => trim($request->username),
                'mail'       => trim($request->emailresponsable ?? ''),
                'password'   => Hash::make($request->password ?? \Illuminate\Support\Str::random(12)),
                'type_id'    => 3,
                'telephone'  => trim($request->telefonoresponsable ?? ''),
                'status'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('holdings')->insert([
                'user_id'             => $userId,
                'representantelegal'  => trim($request->representantelegal ?? ''),
                'rutholding'          => trim($request->rutholding ?? ''),
                'emailresponsable'    => trim($request->emailresponsable ?? ''),
                'telefonoresponsable' => trim($request->telefonoresponsable ?? ''),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        });

        return redirect()->route('admin.holdings')
            ->with('success', 'Holding creado con éxito.');
    }

    public function holdingsEdit($id)
    {
        $this->requireAdmin();

        $h = DB::table('holdings')
            ->join('users', 'users.id', '=', 'holdings.user_id')
            ->select(
                'holdings.id',
                'holdings.user_id',
                'users.name',
                'users.username',
                'holdings.rutholding',
                'holdings.emailresponsable',
                'holdings.telefonoresponsable',
                'holdings.representantelegal'
            )
            ->where('holdings.id', $id)
            ->first();

        if (! $h) {
            abort(404);
        }

        return Inertia::render('Admin/Holdings/Form', [
            'holding' => [
                'id'                  => $h->id,
                'user_id'             => $h->user_id,
                'name'                => $h->name,
                'username'            => $h->username,
                'rutholding'          => $h->rutholding,
                'emailresponsable'    => $h->emailresponsable,
                'telefonoresponsable' => $h->telefonoresponsable,
                'representantelegal'  => $h->representantelegal,
            ],
        ]);
    }

    public function holdingsUpdate(Request $request, $id)
    {
        $this->requireAdmin();

        $h = DB::table('holdings')->where('id', $id)->first();
        if (! $h) {
            abort(404);
        }

        $request->validate([
            'name' => ['required', 'min:2'],
        ]);

        DB::transaction(function () use ($request, $h) {
            DB::table('users')->where('id', $h->user_id)->update([
                'name'       => trim($request->name),
                'mail'       => trim($request->emailresponsable ?? ''),
                'telephone'  => trim($request->telefonoresponsable ?? ''),
                'updated_at' => now(),
            ]);

            DB::table('holdings')->where('id', $h->id)->update([
                'representantelegal'  => trim($request->representantelegal ?? ''),
                'rutholding'          => trim($request->rutholding ?? ''),
                'emailresponsable'    => trim($request->emailresponsable ?? ''),
                'telefonoresponsable' => trim($request->telefonoresponsable ?? ''),
                'updated_at'          => now(),
            ]);
        });

        return redirect()->route('admin.holdings')
            ->with('success', 'Holding actualizado con éxito.');
    }

    // ─── Staff (helpers compartidos) ────────────────────────────────────────

    private function staffTipo(int $typeStaff): array
    {
        return match ($typeStaff) {
            3  => [
                'label'         => 'Radiólogo',
                'label_plural'  => 'Radiólogos',
                'route_index'   => 'admin.radiologos',
                'route_create'  => 'admin.radiologos.create',
                'route_store'   => 'admin.radiologos.store',
                'route_edit'    => 'admin.radiologos.edit',
                'route_update'  => 'admin.radiologos.update',
                'route_toggle'  => 'admin.radiologos.toggle',
            ],
            6  => [
                'label'         => 'Odontólogo',
                'label_plural'  => 'Odontólogos',
                'route_index'   => 'admin.odontologos',
                'route_create'  => 'admin.odontologos.create',
                'route_store'   => 'admin.odontologos.store',
                'route_edit'    => 'admin.odontologos.edit',
                'route_update'  => 'admin.odontologos.update',
                'route_toggle'  => 'admin.odontologos.toggle',
            ],
            7  => [
                'label'         => 'Contralor',
                'label_plural'  => 'Contralores',
                'route_index'   => 'admin.contralores',
                'route_create'  => 'admin.contralores.create',
                'route_store'   => 'admin.contralores.store',
                'route_edit'    => 'admin.contralores.edit',
                'route_update'  => 'admin.contralores.update',
                'route_toggle'  => 'admin.contralores.toggle',
            ],
            11 => [
                'label'         => 'Técnico',
                'label_plural'  => 'Técnicos',
                'route_index'   => 'admin.tecnicos',
                'route_create'  => 'admin.tecnicos.create',
                'route_store'   => 'admin.tecnicos.store',
                'route_edit'    => 'admin.tecnicos.edit',
                'route_update'  => 'admin.tecnicos.update',
                'route_toggle'  => 'admin.tecnicos.toggle',
            ],
            default => [],
        };
    }

    private function staffIndex(Request $request, int $typeStaff): \Inertia\Response
    {
        $this->requireAdmin();
        $search  = trim($request->get('search', ''));
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 15;

        $query = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.type_staff', $typeStaff)
            ->select('s.id', 'u.name', 'u.username', 'u.mail', 'u.telephone', 's.rut', 's.activo');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                  ->orWhere('u.username', 'like', "%{$search}%")
                  ->orWhere('s.rut', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $rows  = $query->orderBy('u.name')->offset(($page - 1) * $perPage)->limit($perPage)->get();

        $ids      = $rows->pluck('id');
        $clinicas = DB::table('clinic_staff as cs')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->whereIn('cs.staff_id', $ids)
            ->select('cs.staff_id', 'uc.name')
            ->get()
            ->groupBy('staff_id');

        $staff = $rows->map(fn ($s) => [
            'id'       => $s->id,
            'name'     => $s->name,
            'username' => $s->username,
            'mail'     => $s->mail,
            'telephone'=> $s->telephone,
            'rut'      => $s->rut,
            'activo'   => (bool) $s->activo,
            'clinicas' => ($clinicas[$s->id] ?? collect())->pluck('name')->all(),
        ]);

        return Inertia::render('Admin/Staff/Index', [
            'staff'       => $staff,
            'total'       => $total,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'filters'     => ['search' => $search],
            'tipo'        => $this->staffTipo($typeStaff),
        ]);
    }

    private function staffCreate(int $typeStaff): \Inertia\Response
    {
        $this->requireAdmin();
        return Inertia::render('Admin/Staff/Form', [
            'staff'       => null,
            'tipo'        => $this->staffTipo($typeStaff),
            'clinicasList'=> $this->clinicasOptions(),
        ]);
    }

    private function staffStore(Request $request, int $typeStaff, int $userTypeId): \Illuminate\Http\RedirectResponse
    {
        $this->requireAdmin();
        $request->validate([
            'name'     => ['required', 'min:2'],
            'username' => ['required', 'unique:users,username'],
            'password' => ['required', 'min:6'],
        ]);

        DB::transaction(function () use ($request, $typeStaff, $userTypeId) {
            $userId = DB::table('users')->insertGetId([
                'name'       => trim($request->name),
                'username'   => trim($request->username),
                'mail'       => trim($request->mail ?? ''),
                'password'   => Hash::make($request->password),
                'type_id'    => $userTypeId,
                'telephone'  => trim($request->telephone ?? ''),
                'status'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $staffId = DB::table('staffs')->insertGetId([
                'user_id'    => $userId,
                'type_staff' => $typeStaff,
                'rut'        => trim($request->rut ?? ''),
                'activo'     => 1,
                'externo'    => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ((array) ($request->clinica_ids ?? []) as $clinicId) {
                DB::table('clinic_staff')->insertOrIgnore([
                    'clinic_id' => (int) $clinicId,
                    'staff_id'  => $staffId,
                ]);
            }
        });

        $tipo = $this->staffTipo($typeStaff);
        return redirect()->route($tipo['route_index'])->with('success', "{$tipo['label']} creado con éxito.");
    }

    private function staffEdit(int $id, int $typeStaff): \Inertia\Response
    {
        $this->requireAdmin();
        $s = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $id)->where('s.type_staff', $typeStaff)
            ->select('s.id', 's.user_id', 'u.name', 'u.username', 'u.mail', 'u.telephone', 's.rut', 's.activo')
            ->first();

        abort_if(! $s, 404);

        $clinicaIds = DB::table('clinic_staff')->where('staff_id', $id)->pluck('clinic_id')->all();

        return Inertia::render('Admin/Staff/Form', [
            'staff' => [
                'id'          => $s->id,
                'user_id'     => $s->user_id,
                'name'        => $s->name,
                'username'    => $s->username,
                'mail'        => $s->mail,
                'telephone'   => $s->telephone,
                'rut'         => $s->rut,
                'activo'      => (bool) $s->activo,
                'clinica_ids' => $clinicaIds,
            ],
            'tipo'        => $this->staffTipo($typeStaff),
            'clinicasList'=> $this->clinicasOptions(),
        ]);
    }

    private function staffUpdate(Request $request, int $id, int $typeStaff): \Illuminate\Http\RedirectResponse
    {
        $this->requireAdmin();
        $s = DB::table('staffs')->where('id', $id)->where('type_staff', $typeStaff)->first();
        abort_if(! $s, 404);

        $request->validate(['name' => ['required', 'min:2']]);

        DB::transaction(function () use ($request, $s, $id) {
            $userData = [
                'name'       => trim($request->name),
                'mail'       => trim($request->mail ?? ''),
                'telephone'  => trim($request->telephone ?? ''),
                'updated_at' => now(),
            ];
            if (! empty($request->password)) {
                $request->validate(['password' => ['min:6']]);
                $userData['password'] = Hash::make($request->password);
            }
            DB::table('users')->where('id', $s->user_id)->update($userData);

            DB::table('staffs')->where('id', $id)->update([
                'rut'        => trim($request->rut ?? ''),
                'updated_at' => now(),
            ]);

            DB::table('clinic_staff')->where('staff_id', $id)->delete();
            foreach ((array) ($request->clinica_ids ?? []) as $clinicId) {
                DB::table('clinic_staff')->insertOrIgnore([
                    'clinic_id' => (int) $clinicId,
                    'staff_id'  => $id,
                ]);
            }
        });

        $tipo = $this->staffTipo($typeStaff);
        return redirect()->route($tipo['route_index'])->with('success', "{$tipo['label']} actualizado con éxito.");
    }

    private function staffToggle(int $id, int $typeStaff): \Illuminate\Http\RedirectResponse
    {
        $this->requireAdmin();
        $s = DB::table('staffs')->where('id', $id)->where('type_staff', $typeStaff)->first();
        abort_if(! $s, 404);

        $newActivo = $s->activo ? 0 : 1;
        DB::table('staffs')->where('id', $id)->update(['activo' => $newActivo, 'updated_at' => now()]);
        DB::table('users')->where('id', $s->user_id)->update(['status' => $newActivo, 'updated_at' => now()]);

        $tipo = $this->staffTipo($typeStaff);
        return back()->with('success', $newActivo ? "{$tipo['label']} activado." : "{$tipo['label']} desactivado.");
    }

    private function clinicasOptions(): \Illuminate\Support\Collection
    {
        return DB::table('clinics as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->select('c.id', 'u.name')
            ->orderBy('u.name')
            ->get()
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }

    // ─── Radiólogos ──────────────────────────────────────────────────────────

    public function radiologos(Request $request)       { return $this->staffIndex($request, 3); }
    public function radiologosCreate()                  { return $this->staffCreate(3); }
    public function radiologosStore(Request $request)   { return $this->staffStore($request, 3, 5); }
    public function radiologosEdit($id)                 { return $this->staffEdit($id, 3); }
    public function radiologosUpdate(Request $request, $id) { return $this->staffUpdate($request, $id, 3); }
    public function radiologosToggle($id)               { return $this->staffToggle($id, 3); }

    // ─── Odontólogos ─────────────────────────────────────────────────────────

    public function odontologos(Request $request)       { return $this->staffIndex($request, 6); }
    public function odontologosCreate()                  { return $this->staffCreate(6); }
    public function odontologosStore(Request $request)   { return $this->staffStore($request, 6, 6); }
    public function odontologosEdit($id)                 { return $this->staffEdit($id, 6); }
    public function odontologosUpdate(Request $request, $id) { return $this->staffUpdate($request, $id, 6); }
    public function odontologosToggle($id)               { return $this->staffToggle($id, 6); }

    // ─── Técnicos ────────────────────────────────────────────────────────────

    public function tecnicos(Request $request)          { return $this->staffIndex($request, 11); }
    public function tecnicosCreate()                     { return $this->staffCreate(11); }
    public function tecnicosStore(Request $request)      { return $this->staffStore($request, 11, 11); }
    public function tecnicosEdit($id)                    { return $this->staffEdit($id, 11); }
    public function tecnicosUpdate(Request $request, $id){ return $this->staffUpdate($request, $id, 11); }
    public function tecnicosToggle($id)                  { return $this->staffToggle($id, 11); }

    // ─── Clínicas ───────────────────────────────────────────────────────────

    public function clinicas(Request $request)
    {
        $this->requireAdmin();

        $search    = trim($request->get('search', ''));
        $holdingId = $request->get('holding_id', '');
        $page      = max(1, (int) $request->get('page', 1));
        $perPage   = 15;

        $query = DB::table('clinics')
            ->join('users', 'users.id', '=', 'clinics.user_id')
            ->leftJoin('holdings', 'holdings.id', '=', 'clinics.holding_id')
            ->leftJoin('users as hu', 'hu.id', '=', 'holdings.user_id')
            ->select(
                'clinics.id',
                'users.name',
                'clinics.address',
                'clinics.telephoneone',
                'clinics.holding_id',
                'hu.name as holding_name'
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                  ->orWhere('clinics.address', 'like', "%{$search}%");
            });
        }

        if ($holdingId !== '' && $holdingId !== null) {
            $query->where('clinics.holding_id', (int) $holdingId);
        }

        $total   = $query->count();
        $clinics = $query->orderBy('users.name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Attach staff counts
        $ids    = $clinics->pluck('id');
        $counts = DB::table('clinic_staff')
            ->whereIn('clinic_id', $ids)
            ->selectRaw('clinic_id, count(*) as cnt')
            ->groupBy('clinic_id')
            ->pluck('cnt', 'clinic_id');

        $clinics = $clinics->map(fn ($c) => [
            'id'           => $c->id,
            'name'         => $c->name,
            'address'      => $c->address,
            'telephoneone' => $c->telephoneone,
            'holding_id'   => $c->holding_id,
            'holding_name' => $c->holding_name ?? '-',
            'staff_count'  => $counts[$c->id] ?? 0,
        ]);

        // Holdings for filter dropdown
        $holdingsList = DB::table('holdings')
            ->join('users', 'users.id', '=', 'holdings.user_id')
            ->select('holdings.id', 'users.name')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($h) => ['value' => $h->id, 'label' => $h->name]);

        return Inertia::render('Admin/Clinicas/Index', [
            'clinicas'     => $clinics,
            'total'        => $total,
            'currentPage'  => $page,
            'perPage'      => $perPage,
            'filters'      => ['search' => $search, 'holding_id' => $holdingId],
            'holdingsList' => $holdingsList,
        ]);
    }

    public function clinicasCreate()
    {
        $this->requireAdmin();

        $holdingsList = DB::table('holdings')
            ->join('users', 'users.id', '=', 'holdings.user_id')
            ->select('holdings.id', 'users.name')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($h) => ['value' => $h->id, 'label' => $h->name]);

        return Inertia::render('Admin/Clinicas/Form', [
            'clinica'      => null,
            'holdingsList' => $holdingsList,
        ]);
    }

    public function clinicasStore(Request $request)
    {
        $this->requireAdmin();

        $request->validate([
            'name'       => ['required', 'min:2'],
            'username'   => ['required', 'unique:users,username'],
            'holding_id' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($request) {
            $userId = DB::table('users')->insertGetId([
                'name'       => trim($request->name),
                'username'   => trim($request->username),
                'mail'       => '',
                'password'   => Hash::make($request->password ?? \Illuminate\Support\Str::random(12)),
                'type_id'    => 4,
                'telephone'  => trim($request->telephoneone ?? ''),
                'status'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('clinics')->insert([
                'user_id'    => $userId,
                'holding_id' => (int) $request->holding_id,
                'address'    => trim($request->address ?? ''),
                'telephoneone' => trim($request->telephoneone ?? ''),
                'telephonetwo' => '',
                'responsable'  => '',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        });

        return redirect()->route('admin.clinicas')
            ->with('success', 'Clínica creada con éxito.');
    }

    public function clinicasEdit($id)
    {
        $this->requireAdmin();

        $c = DB::table('clinics')
            ->join('users', 'users.id', '=', 'clinics.user_id')
            ->select(
                'clinics.id',
                'clinics.user_id',
                'users.name',
                'users.username',
                'clinics.holding_id',
                'clinics.address',
                'clinics.telephoneone'
            )
            ->where('clinics.id', $id)
            ->first();

        if (! $c) {
            abort(404);
        }

        $holdingsList = DB::table('holdings')
            ->join('users', 'users.id', '=', 'holdings.user_id')
            ->select('holdings.id', 'users.name')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($h) => ['value' => $h->id, 'label' => $h->name]);

        return Inertia::render('Admin/Clinicas/Form', [
            'clinica' => [
                'id'           => $c->id,
                'user_id'      => $c->user_id,
                'name'         => $c->name,
                'username'     => $c->username,
                'holding_id'   => $c->holding_id,
                'address'      => $c->address,
                'telephoneone' => $c->telephoneone,
            ],
            'holdingsList' => $holdingsList,
        ]);
    }

    public function clinicasUpdate(Request $request, $id)
    {
        $this->requireAdmin();

        $c = DB::table('clinics')->where('id', $id)->first();
        if (! $c) {
            abort(404);
        }

        $request->validate([
            'name'       => ['required', 'min:2'],
            'holding_id' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($request, $c) {
            DB::table('users')->where('id', $c->user_id)->update([
                'name'       => trim($request->name),
                'updated_at' => now(),
            ]);

            DB::table('clinics')->where('id', $c->id)->update([
                'holding_id'   => (int) $request->holding_id,
                'address'      => trim($request->address ?? ''),
                'telephoneone' => trim($request->telephoneone ?? ''),
                'updated_at'   => now(),
            ]);
        });

        return redirect()->route('admin.clinicas')
            ->with('success', 'Clínica actualizada con éxito.');
    }

    // ─── Secretarías ────────────────────────────────────────────────────────

    private function userTypeIndex(Request $request, int $typeId, string $view, string $label): \Inertia\Response
    {
        $this->requireAdmin();
        $search  = trim($request->get('search', ''));
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 15;

        $query = DB::table('users')->where('type_id', $typeId);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('mail', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $users = $query->select('id', 'name', 'username', 'mail', 'telephone', 'status')
            ->orderBy('name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'username'  => $u->username,
                'mail'      => $u->mail,
                'telephone' => $u->telephone,
                'status'    => (int) $u->status,
            ]);

        return Inertia::render($view, [
            'users'       => $users,
            'total'       => $total,
            'currentPage' => $page,
            'perPage'     => $perPage,
            'filters'     => ['search' => $search],
            'label'       => $label,
        ]);
    }

    private function userTypeCreate(string $view, string $label, string $routeIndex): \Inertia\Response
    {
        $this->requireAdmin();
        return Inertia::render($view, ['user' => null, 'label' => $label, 'routeIndex' => $routeIndex]);
    }

    private function userTypeStore(Request $request, int $typeId, string $routeIndex, string $label): \Illuminate\Http\RedirectResponse
    {
        $this->requireAdmin();
        $request->validate([
            'name'     => ['required', 'min:2'],
            'username' => ['required', 'unique:users,username'],
            'password' => ['required', 'min:6'],
        ]);

        DB::table('users')->insert([
            'name'       => trim($request->name),
            'username'   => trim($request->username),
            'mail'       => trim($request->mail ?? ''),
            'password'   => Hash::make($request->password),
            'type_id'    => $typeId,
            'telephone'  => trim($request->telephone ?? ''),
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route($routeIndex)->with('success', "{$label} creada/o con éxito.");
    }

    private function userTypeEdit(int $id, int $typeId, string $view, string $label, string $routeIndex): \Inertia\Response
    {
        $this->requireAdmin();
        $u = DB::table('users')->where('id', $id)->where('type_id', $typeId)->first();
        abort_if(! $u, 404);

        return Inertia::render($view, [
            'user' => [
                'id'        => $u->id,
                'name'      => $u->name,
                'username'  => $u->username,
                'mail'      => $u->mail,
                'telephone' => $u->telephone,
                'status'    => (int) $u->status,
            ],
            'label'      => $label,
            'routeIndex' => $routeIndex,
        ]);
    }

    private function userTypeUpdate(Request $request, int $id, int $typeId, string $routeIndex, string $label): \Illuminate\Http\RedirectResponse
    {
        $this->requireAdmin();
        $u = DB::table('users')->where('id', $id)->where('type_id', $typeId)->first();
        abort_if(! $u, 404);

        $request->validate([
            'name'     => ['required', 'min:2'],
            'username' => ['required', "unique:users,username,{$id}"],
        ]);

        $data = [
            'name'       => trim($request->name),
            'username'   => trim($request->username),
            'mail'       => trim($request->mail ?? ''),
            'telephone'  => trim($request->telephone ?? ''),
            'updated_at' => now(),
        ];

        if (! empty($request->password)) {
            $request->validate(['password' => ['min:6']]);
            $data['password'] = Hash::make($request->password);
        }

        DB::table('users')->where('id', $id)->update($data);
        return redirect()->route($routeIndex)->with('success', "{$label} actualizada/o con éxito.");
    }

    private function userTypeToggle(int $id, int $typeId, string $routeIndex, string $label): \Illuminate\Http\RedirectResponse
    {
        $this->requireAdmin();
        $u = DB::table('users')->where('id', $id)->where('type_id', $typeId)->first();
        abort_if(! $u, 404);

        $new = $u->status ? 0 : 1;
        DB::table('users')->where('id', $id)->update(['status' => $new, 'updated_at' => now()]);
        return back()->with('success', $new ? "{$label} activada/o." : "{$label} desactivada/o.");
    }

    public function secretarias(Request $request)            { return $this->userTypeIndex($request, 2, 'Admin/Secretarias/Index', 'Secretaria'); }
    public function secretariasCreate()                       { return $this->userTypeCreate('Admin/Secretarias/Form', 'Secretaria', 'admin.secretarias'); }
    public function secretariasStore(Request $request)        { return $this->userTypeStore($request, 2, 'admin.secretarias', 'Secretaria'); }
    public function secretariasEdit($id)                      { return $this->userTypeEdit($id, 2, 'Admin/Secretarias/Form', 'Secretaria', 'admin.secretarias'); }
    public function secretariasUpdate(Request $request, $id)  { return $this->userTypeUpdate($request, $id, 2, 'admin.secretarias', 'Secretaria'); }
    public function secretariasToggle($id)                    { return $this->userTypeToggle($id, 2, 'admin.secretarias', 'Secretaria'); }

    // ─── Contralores ─────────────────────────────────────────────────────────

    public function contralores(Request $request)             { return $this->staffIndex($request, 7); }
    public function contralorCreate()                          { return $this->staffCreate(7); }
    public function contralorStore(Request $request)           { return $this->staffStore($request, 7, 7); }
    public function contralorEdit($id)                         { return $this->staffEdit($id, 7); }
    public function contralorUpdate(Request $request, $id)     { return $this->staffUpdate($request, $id, 7); }
    public function contralorToggle($id)                       { return $this->staffToggle($id, 7); }
}
