<?php

use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\OdontologoController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\RadiologoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v3')->middleware('auth.api')->group(function () {

    // ── Pacientes ──────────────────────────────────────────────────────────
    Route::get('/patient/{rut}',    [PatientController::class, 'findByRut']);
    Route::post('/patient',         [PatientController::class, 'create']);
    Route::put('/patient/{rut}',    [PatientController::class, 'update']);

    // ── Órdenes ────────────────────────────────────────────────────────────
    Route::get('/order/examinations/types',          [OrderController::class, 'examTypes']);
    Route::get('/order/examinations/groups',         [OrderController::class, 'examGroups']);
    Route::get('/order/by-patient/{rut}',            [OrderController::class, 'listByPatient']);
    Route::get('/order/by-radiologo/{rut}',          [OrderController::class, 'listByRadiologo']);
    Route::get('/order/by-id/{id}',                  [OrderController::class, 'byId']);
    Route::post('/order',                            [OrderController::class, 'create']);
    Route::put('/order/{id}',                        [OrderController::class, 'update']);
    Route::post('/order/{id}/files/{examinationId}', [OrderController::class, 'uploadFiles']);
    Route::delete('/order/file/{fileId}',            [OrderController::class, 'deleteFile']);
    Route::patch('/order/{id}/send/radiologo',       [OrderController::class, 'sendToRadiologo']);
    Route::post('/order/{id}/answers',               [OrderController::class, 'saveAnswers']);
    Route::get('/order/pdf/{id}',                    [OrderController::class, 'generatePdf']);
    Route::get('/order/zip/{id}',                    [OrderController::class, 'generateZip']);

    // ── Odontólogos ────────────────────────────────────────────────────────
    Route::get('/odontologo/by-rut/{rut}',  [OdontologoController::class, 'findByRut']);
    Route::get('/odontologo/by-holding',    [OdontologoController::class, 'listByHolding']);
    Route::post('/odontologo/create',       [OdontologoController::class, 'create']);

    // ── Clínicas ───────────────────────────────────────────────────────────
    Route::get('/clinic/by-holding',        [ClinicController::class, 'listByHolding']);

    // ── Radiólogos ─────────────────────────────────────────────────────────
    Route::get('/radiologo/by-rut/{rut}',   [RadiologoController::class, 'findByRut']);
    Route::get('/radiologo/by-holding',     [RadiologoController::class, 'findByHolding']);
    Route::post('/radiologo',               [RadiologoController::class, 'create']);
    Route::put('/radiologo/{rut}',          [RadiologoController::class, 'update']);
    Route::post('/radiologo/firma/{rut}',   [RadiologoController::class, 'setFirma']);
    Route::delete('/radiologo/{rut}',       [RadiologoController::class, 'destroy']);
});
