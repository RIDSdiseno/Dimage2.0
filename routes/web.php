<?php

use App\Http\Controllers\AdministracionController;
use App\Http\Controllers\ExamenesController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ContraloriaController;
use App\Http\Controllers\ExcelController;
use App\Http\Controllers\IntegracionesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('login', [AuthController::class, 'login'])->middleware('guest');
Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Selector de región (sesión) — disponible sin auth para evitar problemas de redirect
Route::post('/region', [RegionController::class, 'update'])->name('region.update')->middleware('auth');

// App (autenticado)
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Perfil (todos los usuarios autenticados)
    // Calendario (todos los usuarios autenticados)
    Route::get('/calendario',               [CalendarController::class, 'index'])->name('calendario');
    Route::get('/calendario/events',        [CalendarController::class, 'events'])->name('calendario.events');
    Route::post('/calendario',              [CalendarController::class, 'store'])->name('calendario.store');
    Route::put('/calendario/{event}',       [CalendarController::class, 'update'])->name('calendario.update');
    Route::delete('/calendario/{event}',    [CalendarController::class, 'destroy'])->name('calendario.destroy');

    Route::get('/perfil',           [ProfileController::class, 'show'])->name('perfil');
    Route::put('/perfil',           [ProfileController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/password',  [ProfileController::class, 'password'])->name('perfil.password');

    // Files (S3 signed URLs) - todos los autenticados
    Route::get('/archivos/{id}',    [FileController::class, 'serve'])->name('archivos.serve');
    Route::delete('/archivos/{id}', [FileController::class, 'destroy'])->name('archivos.destroy');

    // Pacientes - admin, secretaria, holding, clínica, odontólogo, técnico (sin radiólogo)
    Route::middleware('role:1,2,3,4,6,11')->group(function () {
        Route::get('/pacientes',                  [PatientController::class, 'index'])->name('pacientes.index');
        Route::get('/pacientes/search',           [PatientController::class, 'search'])->name('pacientes.search');
        Route::get('/pacientes/crear',            [PatientController::class, 'create'])->name('pacientes.create');
        Route::post('/pacientes',                 [PatientController::class, 'store'])->name('pacientes.store');
        Route::get('/pacientes/{patient}/editar', [PatientController::class, 'edit'])->name('pacientes.edit');
        Route::put('/pacientes/{patient}',        [PatientController::class, 'update'])->name('pacientes.update');
    });

    // Órdenes (static segments first to avoid {order} wildcard conflicts)
    Route::get('/ordenes',        [OrderController::class, 'index'])->name('ordenes.index');
    Route::get('/ordenes/search', [OrderController::class, 'search'])->name('ordenes.search');

    // Órdenes - crear (static segments before {order} wildcard)
    Route::middleware('role:1,2,4,6,11')->group(function () {
        Route::get('/ordenes/crear',  [OrderController::class, 'create'])->name('ordenes.create');
        Route::post('/ordenes',       [OrderController::class, 'store'])->name('ordenes.store');
        Route::get('/ordenes/ajax/patients',    [OrderController::class, 'getPatients'])->name('ordenes.patients');
        Route::get('/ordenes/ajax/odontologos', [OrderController::class, 'getOdontologos'])->name('ordenes.odontologos');
        Route::get('/ordenes/ajax/radiologos',  [OrderController::class, 'getRadiologos'])->name('ordenes.radiologos');
    });

    // Órdenes - editar: admin, secretaria, clínica, odontólogo, técnico
    Route::middleware('role:1,2,4,6,11')->group(function () {
        Route::get('/ordenes/{order}/editar', [OrderController::class, 'edit'])->name('ordenes.edit');
        Route::post('/ordenes/{order}',       [OrderController::class, 'update'])->name('ordenes.update');
    });

    // Órdenes - ver/descargar por ID: todos los autenticados
    Route::get('/ordenes/{order}',        [OrderController::class, 'show'])->name('ordenes.show');
    Route::get('/ordenes/{order}/pdf',    [OrderController::class, 'pdf'])->name('ordenes.pdf');
    Route::get('/ordenes/{order}/zip',    [OrderController::class, 'zip'])->name('ordenes.zip');

    // Órdenes - responder: admin, secretaria, radiólogo, técnico
    Route::middleware('role:1,2,5,11')->group(function () {
        Route::get('/ordenes/{order}/responder',  [OrderController::class, 'responder'])->name('ordenes.responder');
        Route::post('/ordenes/{order}/responder', [OrderController::class, 'doResponder'])->name('ordenes.doResponder');
    });

    // Órdenes - eliminar orden (admin only, extra check inside controller)
    Route::delete('/ordenes/{order}', [OrderController::class, 'destroy'])->name('ordenes.destroy');

    // Órdenes - eliminar examen: admin, secretaria, clínica, odontólogo, técnico
    Route::middleware('role:1,2,4,6,11')->group(function () {
        Route::delete('/ordenes/{order}/examenes/{examination}', [OrderController::class, 'deleteExamen'])->name('ordenes.examenes.destroy');
    });

    // Admin - admin y secretaria
    Route::middleware('role:1,2')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/crear', [AdminController::class, 'adminCreate'])->name('create');
        Route::post('/', [AdminController::class, 'adminStore'])->name('store');
        Route::get('/{id}/editar', [AdminController::class, 'adminEdit'])->name('edit');
        Route::put('/{id}', [AdminController::class, 'adminUpdate'])->name('update');
        Route::post('/{id}/toggle', [AdminController::class, 'adminToggle'])->name('toggle');
        Route::get('/usuarios', [AdminController::class, 'usuarios'])->name('usuarios');
        Route::get('/usuarios/crear', [AdminController::class, 'usuariosCreate'])->name('usuarios.create');
        Route::post('/usuarios', [AdminController::class, 'usuariosStore'])->name('usuarios.store');
        Route::get('/usuarios/{user}/editar', [AdminController::class, 'usuariosEdit'])->name('usuarios.edit');
        Route::put('/usuarios/{user}', [AdminController::class, 'usuariosUpdate'])->name('usuarios.update');
        Route::post('/usuarios/{user}/toggle', [AdminController::class, 'usuariosToggle'])->name('usuarios.toggle');
        Route::get('/holdings', [AdminController::class, 'holdings'])->name('holdings');
        Route::get('/holdings/crear', [AdminController::class, 'holdingsCreate'])->name('holdings.create');
        Route::post('/holdings', [AdminController::class, 'holdingsStore'])->name('holdings.store');
        Route::get('/holdings/{id}/editar', [AdminController::class, 'holdingsEdit'])->name('holdings.edit');
        Route::put('/holdings/{id}', [AdminController::class, 'holdingsUpdate'])->name('holdings.update');
        Route::get('/clinicas', [AdminController::class, 'clinicas'])->name('clinicas');
        Route::get('/clinicas/crear', [AdminController::class, 'clinicasCreate'])->name('clinicas.create');
        Route::post('/clinicas', [AdminController::class, 'clinicasStore'])->name('clinicas.store');
        Route::get('/clinicas/{id}/editar', [AdminController::class, 'clinicasEdit'])->name('clinicas.edit');
        Route::put('/clinicas/{id}', [AdminController::class, 'clinicasUpdate'])->name('clinicas.update');

        // Radiólogos
        Route::get('/radiologos', [AdminController::class, 'radiologos'])->name('radiologos');
        Route::get('/radiologos/crear', [AdminController::class, 'radiologosCreate'])->name('radiologos.create');
        Route::post('/radiologos', [AdminController::class, 'radiologosStore'])->name('radiologos.store');
        Route::get('/radiologos/{id}/editar', [AdminController::class, 'radiologosEdit'])->name('radiologos.edit');
        Route::put('/radiologos/{id}', [AdminController::class, 'radiologosUpdate'])->name('radiologos.update');
        Route::post('/radiologos/{id}/toggle', [AdminController::class, 'radiologosToggle'])->name('radiologos.toggle');

        // Odontólogos
        Route::get('/odontologos', [AdminController::class, 'odontologos'])->name('odontologos');
        Route::get('/odontologos/crear', [AdminController::class, 'odontologosCreate'])->name('odontologos.create');
        Route::post('/odontologos', [AdminController::class, 'odontologosStore'])->name('odontologos.store');
        Route::get('/odontologos/{id}/editar', [AdminController::class, 'odontologosEdit'])->name('odontologos.edit');
        Route::put('/odontologos/{id}', [AdminController::class, 'odontologosUpdate'])->name('odontologos.update');
        Route::post('/odontologos/{id}/toggle', [AdminController::class, 'odontologosToggle'])->name('odontologos.toggle');

        // Técnicos
        Route::get('/tecnicos', [AdminController::class, 'tecnicos'])->name('tecnicos');
        Route::get('/tecnicos/crear', [AdminController::class, 'tecnicosCreate'])->name('tecnicos.create');
        Route::post('/tecnicos', [AdminController::class, 'tecnicosStore'])->name('tecnicos.store');
        Route::get('/tecnicos/{id}/editar', [AdminController::class, 'tecnicosEdit'])->name('tecnicos.edit');
        Route::put('/tecnicos/{id}', [AdminController::class, 'tecnicosUpdate'])->name('tecnicos.update');
        Route::post('/tecnicos/{id}/toggle', [AdminController::class, 'tecnicosToggle'])->name('tecnicos.toggle');

        // Secretarías
        Route::get('/secretarias',              [AdminController::class, 'secretarias'])->name('secretarias');
        Route::get('/secretarias/crear',         [AdminController::class, 'secretariasCreate'])->name('secretarias.create');
        Route::post('/secretarias',              [AdminController::class, 'secretariasStore'])->name('secretarias.store');
        Route::get('/secretarias/{id}/editar',   [AdminController::class, 'secretariasEdit'])->name('secretarias.edit');
        Route::put('/secretarias/{id}',          [AdminController::class, 'secretariasUpdate'])->name('secretarias.update');
        Route::post('/secretarias/{id}/toggle',  [AdminController::class, 'secretariasToggle'])->name('secretarias.toggle');

        // Contralores
        Route::get('/contralores',              [AdminController::class, 'contralores'])->name('contralores');
        Route::get('/contralores/crear',         [AdminController::class, 'contralorCreate'])->name('contralores.create');
        Route::post('/contralores',              [AdminController::class, 'contralorStore'])->name('contralores.store');
        Route::get('/contralores/{id}/editar',   [AdminController::class, 'contralorEdit'])->name('contralores.edit');
        Route::put('/contralores/{id}',          [AdminController::class, 'contralorUpdate'])->name('contralores.update');
        Route::post('/contralores/{id}/toggle',  [AdminController::class, 'contralorToggle'])->name('contralores.toggle');

        // Contraloría
        Route::get('/controloria',              [ContraloriaController::class, 'index'])->name('controloria');
        Route::get('/controloria/crear',         [ContraloriaController::class, 'create'])->name('controloria.create');
        Route::post('/controloria',              [ContraloriaController::class, 'store'])->name('controloria.store');
        Route::get('/controloria/{id}',          [ContraloriaController::class, 'show'])->name('controloria.show');
        Route::post('/controloria/{id}/responder', [ContraloriaController::class, 'respond'])->name('controloria.respond');
        Route::post('/controloria/{id}/archivos',    [ContraloriaController::class, 'addArchive'])->name('controloria.archivos');
        Route::delete('/controloria/archivos/{archive}', [ContraloriaController::class, 'destroyArchive'])->name('controloria.archivos.destroy');
        Route::get('/controloria/{id}/pdf',        [ContraloriaController::class, 'pdf'])->name('controloria.pdf');

        // Integraciones
        Route::get('/integraciones',             [IntegracionesController::class, 'index'])->name('integraciones');
        Route::get('/integraciones/crear',        [IntegracionesController::class, 'create'])->name('integraciones.create');
        Route::post('/integraciones',             [IntegracionesController::class, 'store'])->name('integraciones.store');
        Route::get('/integraciones/{id}/editar',  [IntegracionesController::class, 'edit'])->name('integraciones.edit');
        Route::put('/integraciones/{id}',         [IntegracionesController::class, 'update'])->name('integraciones.update');
        Route::post('/integraciones/{id}/desactivar', [IntegracionesController::class, 'destroy'])->name('integraciones.destroy');

        // Excel (movido fuera de este grupo — ver abajo)

        // Tipos de Examen
        Route::get('/examenes',               [ExamenesController::class, 'index'])->name('examenes');
        Route::get('/examenes/search',        [ExamenesController::class, 'search'])->name('examenes.search');
        Route::get('/examenes/crear',          [ExamenesController::class, 'create'])->name('examenes.create');
        Route::post('/examenes',              [ExamenesController::class, 'store'])->name('examenes.store');
        Route::get('/examenes/{id}/editar',   [ExamenesController::class, 'edit'])->name('examenes.edit');
        Route::put('/examenes/{id}',          [ExamenesController::class, 'update'])->name('examenes.update');

        // Administración
        Route::get('/administracion/corregir',     [AdministracionController::class, 'corregir'])->name('administracion.corregir');
        Route::post('/administracion/enviar-correccion', [AdministracionController::class, 'enviarCorreccion'])->name('administracion.enviar-correccion');
        Route::post('/administracion/no-informada', [AdministracionController::class, 'noInformada'])->name('administracion.no-informada');

        // Feriados
        Route::get('/feriados',              [AdministracionController::class, 'feriados'])->name('feriados');
        Route::get('/feriados/crear',        [AdministracionController::class, 'feriadosCreate'])->name('feriados.create');
        Route::post('/feriados',             [AdministracionController::class, 'feriadosStore'])->name('feriados.store');
        Route::get('/feriados/{id}/editar',  [AdministracionController::class, 'feriadosEdit'])->name('feriados.edit');
        Route::put('/feriados/{id}',         [AdministracionController::class, 'feriadosUpdate'])->name('feriados.update');
        Route::delete('/feriados/{id}',      [AdministracionController::class, 'feriadosDestroy'])->name('feriados.destroy');
    });

    // Excel — admin, secretaria y radiólogo
    Route::middleware('role:1,2,5')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/excel',                 [ExcelController::class, 'index'])->name('excel');
        Route::post('/excel',                [ExcelController::class, 'download'])->name('excel.download');
        Route::post('/excel/por-examen',     [ExcelController::class, 'downloadByExamType'])->name('excel.por-examen');
        Route::post('/excel/por-radiologo',  [ExcelController::class, 'downloadByRadiologo'])->name('excel.por-radiologo');
        Route::post('/excel/espacio',        [ExcelController::class, 'downloadEspacioUso'])->name('excel.espacio');
    });
});
