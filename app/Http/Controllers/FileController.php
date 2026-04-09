<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function destroy(int $id): RedirectResponse
    {
        $file = DB::table('files')->where('id', $id)->first(['id', 'ruta', 'examination_id']);
        abort_if(!$file, 404);

        $orderId = DB::table('examination_order')
            ->where('examination_id', $file->examination_id)
            ->value('order_id');

        $order = DB::table('orders')->where('id', $orderId)->first(['estadoradiologo']);
        abort_if(!$order, 404);

        if ((int) $order->estadoradiologo === 1) {
            return back()->with('error', 'No se puede eliminar archivos de una orden ya respondida.');
        }

        if ($file->ruta) {
            try { Storage::disk('s3')->delete($file->ruta); } catch (\Throwable) {}
        }

        DB::table('files')->where('id', $id)->delete();

        return back()->with('success', 'Archivo eliminado.');
    }

    /**
     * Stream a file directly from S3 so the browser can display it inline.
     */
    public function serve(int $id): Response
    {
        $file = DB::table('files')->where('id', $id)->first(['ruta', 'name', 'extension']);

        abort_if(!$file || !$file->ruta, 404);

        $ext  = strtolower($file->extension ?? pathinfo($file->ruta, PATHINFO_EXTENSION));
        $mime = $this->mime($ext);
        $name = $file->name ?: basename($file->ruta);

        $content = Storage::disk('s3')->get($file->ruta);

        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . rawurlencode($name) . '"')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    private function mime(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            'pdf'         => 'application/pdf',
            'zip'         => 'application/zip',
            'rar'         => 'application/x-rar-compressed',
            default       => 'application/octet-stream',
        };
    }
}
