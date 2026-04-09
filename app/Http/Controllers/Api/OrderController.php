<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    // GET /api/v3/order/examinations/types
    public function examTypes()
    {
        $kinds = DB::table('kinds')
            ->select('id', 'descipcion as descripcion', 'group as grupo')
            ->orderBy('group')
            ->orderBy('descipcion')
            ->get();

        return response()->json($kinds);
    }

    // GET /api/v3/order/examinations/groups
    public function examGroups()
    {
        $groups = DB::table('kinds')
            ->select('group as grupo')
            ->distinct()
            ->orderBy('group')
            ->pluck('grupo');

        return response()->json($groups);
    }

    // GET /api/v3/order/by-patient/{rut}
    public function listByPatient(Request $request, string $rut)
    {
        $patient = DB::table('patients')->where('rut', $rut)->first('id');
        if (! $patient) return response()->json(['error' => 'Paciente no encontrado.'], 404);

        $page    = max(1, (int) $request->get('page', 1));
        $perPage = (int) $request->get('per_page', 15);

        $query = DB::table('orders as o')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->where('o.patient_id', $patient->id)
            ->select('o.id', 'o.estadoradiologo', 'o.prioridad', 'o.created_at', 'uc.name as clinica');

        $total  = $query->count();
        $orders = $query->orderByDesc('o.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($o) => [
                'id'              => $o->id,
                'clinica'         => $o->clinica,
                'prioridad'       => $o->prioridad,
                'estadoradiologo' => $o->estadoradiologo,
                'created_at'      => $o->created_at,
            ]);

        return response()->json([
            'data'        => $orders,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    // GET /api/v3/order/by-id/{id}
    public function byId(Request $request, int $id)
    {
        $order = DB::table('orders as o')
            ->join('patients as p', 'p.id', '=', 'o.patient_id')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->where('o.id', $id)
            ->select('o.*', 'p.name as paciente', 'p.rut as rut_paciente', 'uc.name as clinica')
            ->first();

        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        $examenes = DB::table('examinations as e')
            ->join('examination_order as eo', 'eo.examination_id', '=', 'e.id')
            ->join('kinds as k', 'k.id', '=', 'e.kind_id')
            ->where('eo.order_id', $id)
            ->select('e.id', 'k.descipcion as descripcion', 'k.group as grupo', 'e.url_texto')
            ->get()
            ->map(function ($e) {
                $archivos = DB::table('files')
                    ->where('examination_id', $e->id)
                    ->get(['id', 'name', 'extension', 'file_size', 'desde_informar'])
                    ->map(fn ($f) => [
                        'id'            => $f->id,
                        'name'          => $f->name,
                        'extension'     => $f->extension,
                        'file_size'     => $f->file_size,
                        'desde_informar'=> (bool) $f->desde_informar,
                    ]);

                $respuesta = DB::table('answers')
                    ->where('examination_id', $e->id)
                    ->first(['campo_1', 'solo_adjunto']);

                return [
                    'id'          => $e->id,
                    'descripcion' => $e->descripcion,
                    'grupo'       => $e->grupo,
                    'url_texto'   => $e->url_texto,
                    'archivos'    => $archivos,
                    'respuesta'   => $respuesta ? [
                        'texto'        => $respuesta->campo_1,
                        'solo_adjunto' => (bool) $respuesta->solo_adjunto,
                    ] : null,
                ];
            });

        return response()->json([
            'id'              => $order->id,
            'paciente'        => $order->paciente,
            'rut_paciente'    => $order->rut_paciente,
            'clinica'         => $order->clinica,
            'diagnostico'     => $order->diagnostico,
            'observaciones'   => $order->observaciones,
            'prioridad'       => $order->prioridad,
            'estadoradiologo' => $order->estadoradiologo,
            'estadoodontologo'=> $order->estadoodontologo,
            'enviada'         => $order->enviada,
            'respondida'      => $order->respondida,
            'created_at'      => $order->created_at,
            'examenes'        => $examenes,
        ]);
    }

    // POST /api/v3/order
    public function create(Request $request)
    {
        $data = $request->validate([
            'patient_id'    => ['required', 'exists:patients,id'],
            'clinic_id'     => ['required', 'exists:clinics,id'],
            'diagnostico'   => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'prioridad'     => ['nullable', 'in:Normal,Urgente'],
            'examenes'      => ['required', 'array', 'min:1'],
            'examenes.*.kind_id' => ['required', 'exists:kinds,id'],
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'patient_id'      => $data['patient_id'],
            'clinic_id'       => $data['clinic_id'],
            'diagnostico'     => $data['diagnostico'] ?? null,
            'observaciones'   => $data['observaciones'] ?? null,
            'prioridad'       => $data['prioridad'] ?? 'Normal',
            'estadoradiologo' => 4,
            'estadoodontologo'=> 4,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        foreach ($data['examenes'] as $ex) {
            $exId = DB::table('examinations')->insertGetId([
                'kind_id'    => $ex['kind_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('examination_order')->insert([
                'order_id'       => $orderId,
                'examination_id' => $exId,
            ]);
        }

        return response()->json(['message' => 'Orden creada.', 'order_id' => $orderId], 201);
    }

    // POST /api/v3/order/{id}/files/{examination_id}
    public function uploadFiles(Request $request, int $id, int $examinationId)
    {
        $exists = DB::table('examination_order')
            ->where('order_id', $id)
            ->where('examination_id', $examinationId)
            ->exists();

        if (! $exists) return response()->json(['error' => 'Examen no pertenece a esta orden.'], 404);

        $request->validate(['archivos' => ['required', 'array'], 'archivos.*' => ['file']]);

        $uploaded = [];
        foreach ($request->file('archivos', []) as $file) {
            $path = $file->store("orders/{$id}/examinations/{$examinationId}", 's3');
            $fileId = DB::table('files')->insertGetId([
                'examination_id' => $examinationId,
                'name'           => $file->getClientOriginalName(),
                'ruta'           => $path,
                'extension'      => strtolower($file->getClientOriginalExtension()),
                'file_size'      => $file->getSize(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $uploaded[] = ['id' => $fileId, 'name' => $file->getClientOriginalName()];
        }

        return response()->json(['message' => 'Archivos subidos.', 'archivos' => $uploaded], 201);
    }

    // DELETE /api/v3/order/file/{fileId}
    public function deleteFile(Request $request, int $fileId)
    {
        $file = DB::table('files')->where('id', $fileId)->first(['id', 'ruta', 'examination_id']);
        if (! $file) return response()->json(['error' => 'Archivo no encontrado.'], 404);

        if ($file->ruta) {
            try { Storage::disk('s3')->delete($file->ruta); } catch (\Throwable) {}
        }

        DB::table('files')->where('id', $fileId)->delete();

        return response()->json(['message' => 'Archivo eliminado.']);
    }

    // PATCH /api/v3/order/{id}/send/radiologo
    public function sendToRadiologo(Request $request, int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first(['id', 'estadoradiologo']);
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        if (! in_array((int) $order->estadoradiologo, [0, 4])) {
            return response()->json(['error' => 'La orden ya fue enviada o respondida.'], 422);
        }

        $data = $request->validate([
            'staff_ids' => ['required', 'array', 'min:1'],
            'staff_ids.*' => ['exists:staffs,id'],
        ]);

        DB::table('orders')->where('id', $id)->update([
            'estadoradiologo' => 0,
            'enviada'         => now(),
            'updated_at'      => now(),
        ]);

        DB::table('order_staff_exam')->where('order_id', $id)->delete();

        $examIds = DB::table('examination_order')
            ->where('order_id', $id)
            ->pluck('examination_id');

        foreach ($data['staff_ids'] as $staffId) {
            foreach ($examIds as $exId) {
                DB::table('order_staff_exam')->insert([
                    'order_id'       => $id,
                    'staff_id'       => $staffId,
                    'examination_id' => $exId,
                ]);
            }
        }

        return response()->json(['message' => 'Orden enviada al radiólogo.']);
    }

    // POST /api/v3/order/{id}/answers
    public function saveAnswers(Request $request, int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first(['id', 'estadoradiologo']);
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        $data = $request->validate([
            'respuestas'                  => ['required', 'array'],
            'respuestas.*.examination_id' => ['required', 'integer'],
            'respuestas.*.texto'          => ['nullable', 'string'],
            'respuestas.*.solo_adjunto'   => ['nullable', 'boolean'],
        ]);

        foreach ($data['respuestas'] as $r) {
            $exId = $r['examination_id'];
            $existing = DB::table('answers')->where('examination_id', $exId)->first('id');

            if ($existing) {
                DB::table('answers')->where('id', $existing->id)->update([
                    'campo_1'      => $r['texto'] ?? null,
                    'solo_adjunto' => $r['solo_adjunto'] ?? false,
                    'updated_at'   => now(),
                ]);
            } else {
                DB::table('answers')->insert([
                    'examination_id' => $exId,
                    'campo_1'        => $r['texto'] ?? null,
                    'solo_adjunto'   => $r['solo_adjunto'] ?? false,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }

        DB::table('orders')->where('id', $id)->update([
            'estadoradiologo' => 1,
            'respondida'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['message' => 'Respuestas guardadas.']);
    }

    // GET /api/v3/order/by-radiologo/{rut}
    public function listByRadiologo(Request $request, string $rut)
    {
        $holdingId = $request->_holding_id;

        $radiologo = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('u.rut', $rut)
            ->where('s.type_staff', 3) // radiólogo
            ->where('c.holding_id', $holdingId)
            ->select('s.id as staff_id', 'u.rut')
            ->first();

        if (! $radiologo) {
            return response()->json(['error' => "Radiólogo de rut $rut no existe en la red."], 404);
        }

        $soloPendientes = $request->boolean('solo_pendientes', false);
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = min(50, max(1, (int) $request->get('per_page', 15)));

        $query = DB::table('orders as o')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->join('patients as p', 'p.id', '=', 'o.patient_id')
            ->where('c.holding_id', $holdingId)
            ->whereExists(function ($sub) use ($radiologo, $soloPendientes) {
                $sub->from('order_staff_exam as ose')
                    ->whereColumn('ose.order_id', 'o.id')
                    ->where('ose.staff_id', $radiologo->staff_id);
                if ($soloPendientes) {
                    $sub->where('ose.respondida', 0);
                }
            })
            ->select(
                'o.id', 'o.estadoradiologo', 'o.estadoodontologo',
                'o.prioridad', 'o.created_at', 'o.enviada', 'o.respondida',
                'p.name as paciente', 'p.rut as rut_paciente',
                'uc.name as clinica'
            );

        if ($soloPendientes) {
            $query->where('o.estadoradiologo', 0);
        }

        $total  = $query->count();
        $orders = $query->orderByDesc('o.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data'        => $orders,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    // PUT /api/v3/order/{id}
    public function update(Request $request, int $id)
    {
        $holdingId = $request->_holding_id;

        $order = DB::table('orders as o')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->where('o.id', $id)
            ->where('c.holding_id', $holdingId)
            ->select('o.id', 'o.estadoradiologo', 'o.estadoodontologo', 'o.enviada')
            ->first();

        if (! $order) {
            return response()->json(['error' => "Orden $id no existe para la red seleccionada."], 404);
        }

        // Solo editable si aún no fue enviada al radiólogo (estadoradiologo = 4 = sin asignar)
        // o si está en corrección (estadoodontologo = 3)
        $editable = (int) $order->estadoradiologo === 4
            || ((int) $order->estadoradiologo === 2 && (int) $order->estadoodontologo === 3);

        if (! $editable) {
            return response()->json(['error' => 'La orden no puede editarse en su estado actual.'], 422);
        }

        $data = $request->validate([
            'diagnostico'    => ['sometimes', 'nullable', 'string'],
            'observaciones'  => ['sometimes', 'nullable', 'string'],
            'observaciones_2'=> ['sometimes', 'nullable', 'string'],
            'prioridad'      => ['sometimes', 'in:Normal,Urgente'],
            'trx_number'     => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if (empty($data)) {
            return response()->json(['error' => 'No se enviaron campos para actualizar.'], 422);
        }

        DB::table('orders')->where('id', $id)->update(
            array_merge($data, ['updated_at' => now()])
        );

        return response()->json(['message' => 'Orden actualizada.']);
    }

    // GET /api/v3/order/pdf/{id}
    public function generatePdf(Request $request, int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first('id');
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        return response()->json([
            'url' => route('ordenes.pdf', $id),
        ]);
    }

    // GET /api/v3/order/zip/{id}
    public function generateZip(Request $request, int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first('id');
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        return response()->json([
            'url' => route('ordenes.zip', $id),
        ]);
    }
}
