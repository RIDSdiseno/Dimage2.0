<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Jobs\ProcessCbctZipTemp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function destroy(int $id): RedirectResponse
    {
        $user = Auth::user();

        $file = DB::table('files')->where('id', $id)->first(['id', 'ruta', 'examination_id']);
        abort_if(!$file, 404);

        $orderId = DB::table('examination_order')
            ->where('examination_id', $file->examination_id)
            ->value('order_id');

        $order = DB::table('orders')->where('id', $orderId)->first(['estadoradiologo']);
        abort_if(!$order, 404);

        $isAdmin = (int) ($user->type_id ?? 0) === 1;
        $estado  = (int) $order->estadoradiologo;

        if ($estado === 1 && !$isAdmin) {
            return back()->with('error', 'No se puede eliminar archivos de una orden ya respondida.');
        }

        if (!$isAdmin && !in_array($estado, [0, 2, 4])) {
            abort(403, 'Sin permiso para eliminar archivos en este estado.');
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
    public function download(int $id): \Illuminate\Http\RedirectResponse|StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $file = DB::table('files')->where('id', $id)->first(['ruta', 'nombre_dcm', 'ruta_dcm', 'name', 'extension']);
        abort_if(!$file || !$file->ruta || $file->ruta === '0', 404);

        // CBCT serie procesada
        if ($file->ruta_dcm && $file->ruta_dcm !== 'processing') {
            // 1. nombre_dcm seteado por ProcessCbctZip — solo válido si apunta a un ZIP real
            $zipPath = ($file->nombre_dcm && strtolower(pathinfo($file->nombre_dcm, PATHINFO_EXTENSION)) === 'zip')
                ? $file->nombre_dcm
                : null;

            // 2. nombre_dcm nulo o apunta a un DCM — buscar cualquier ZIP en el directorio raíz de la orden
            if (!$zipPath) {
                preg_match('/^ordenes\/(\d+)\//', $file->ruta_dcm, $m);
                $orderId = isset($m[1]) ? (int) $m[1] : 0;
                if ($orderId) {
                    $topFiles = Storage::disk('s3')->files("ordenes/{$orderId}");
                    foreach ($topFiles as $tf) {
                        if (strtolower(pathinfo($tf, PATHINFO_EXTENSION)) === 'zip') {
                            $zipPath = $tf;
                            // Cachear para próximas peticiones
                            DB::table('files')->where('id', $id)->update(['nombre_dcm' => $zipPath]);
                            break;
                        }
                    }
                }
            }

            if ($zipPath) {
                // ZIP disponible — presigned URL: el browser descarga directo de S3 (instantáneo)
                try {
                    $zipName = $file->name ?: basename($zipPath);
                    $url = Storage::disk('s3')->temporaryUrl(
                        $zipPath,
                        now()->addMinutes(30),
                        ['ResponseContentDisposition' => 'attachment; filename="' . rawurlencode($zipName) . '"']
                    );
                    return redirect()->to($url);
                } catch (\Throwable) {
                    // S3 driver sin soporte de presigned URLs → stream directo
                    $stream = Storage::disk('s3')->readStream($zipPath);
                    abort_if(!is_resource($stream), 404);
                    $zipName = $file->name ?: basename($zipPath);
                    return response()->stream(function () use ($stream) {
                        fpassthru($stream); if (is_resource($stream)) fclose($stream);
                    }, 200, [
                        'Content-Type'        => 'application/zip',
                        'Content-Disposition' => 'attachment; filename="' . rawurlencode($zipName) . '"',
                        'Cache-Control'       => 'no-cache',
                    ]);
                }
            }

            // 3. ZIP borrado Y no hay ZIP en el directorio — reconstruir desde la serie DCM
            $paths = $this->seriePaths($id, $file->ruta_dcm);
            if (!empty($paths)) {
                $baseName = pathinfo($file->name ?: 'CBCT', PATHINFO_FILENAME);
                $tmpZip   = tempnam(sys_get_temp_dir(), 'cbct_dl_') . '.zip';
                set_time_limit(0);
                ignore_user_abort(true);
                $za = new \ZipArchive();
                if ($za->open($tmpZip, \ZipArchive::CREATE) === true) {
                    foreach ($paths as $path) {
                        $s = Storage::disk('s3')->readStream($path);
                        if (is_resource($s)) {
                            $content = stream_get_contents($s);
                            fclose($s);
                            if ($content !== false) $za->addFromString(basename($path), $content);
                        }
                    }
                    $za->close();
                    return response()->download($tmpZip, $baseName . '.zip', [
                        'Content-Type' => 'application/zip',
                    ])->deleteFileAfterSend(true);
                }
                @unlink($tmpZip);
            }
        }

        // Archivo normal (imagen, PDF, DCM individual) — presigned URL si es posible
        $ext  = strtolower($file->extension ?? pathinfo($file->ruta, PATHINFO_EXTENSION));
        $name = $file->name ?: basename($file->ruta);
        try {
            $url = Storage::disk('s3')->temporaryUrl(
                $file->ruta,
                now()->addMinutes(30),
                ['ResponseContentDisposition' => 'attachment; filename="' . rawurlencode($name) . '"']
            );
            return redirect()->to($url);
        } catch (\Throwable) {}

        // Fallback streaming
        $mime = $this->mime($ext);
        try { $stream = Storage::disk('s3')->readStream($file->ruta); } catch (\Throwable) { abort(404); }
        abort_if(!is_resource($stream), 404);
        return response()->stream(function () use ($stream) {
            fpassthru($stream); if (is_resource($stream)) fclose($stream);
        }, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($name) . '"',
            'Cache-Control'       => 'no-cache',
        ]);
    }

    public function serve(int $id, string $filename = ''): StreamedResponse
    {
        $file = DB::table('files')->where('id', $id)->first(['ruta', 'name', 'extension']);

        abort_if(!$file || !$file->ruta || $file->ruta === '0', 404);

        $ext  = strtolower($file->extension ?? pathinfo($file->ruta, PATHINFO_EXTENSION));
        $mime = $this->mime($ext);
        $name = $file->name ?: basename($file->ruta);

        try {
            $stream = Storage::disk('s3')->readStream($file->ruta);
        } catch (\Throwable) {
            abort(404);
        }

        abort_if(!is_resource($stream), 404);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . rawurlencode($name) . '"',
            'Cache-Control'       => 'private, max-age=3600',
            'Accept-Ranges'       => 'bytes',
        ]);
    }

    /**
     * Return JSON with slice count for a CBCT serie.
     */
    public function serieInfo(int $id): JsonResponse
    {
        $file = DB::table('files')->where('id', $id)->first(['ruta', 'ruta_dcm', 'extension']);
        abort_if(!$file, 404);

        $ext = strtolower($file->extension ?? pathinfo((string) $file->ruta, PATHINFO_EXTENSION));

        // No series yet (null) or still being processed in the background queue
        if (!$file->ruta_dcm || $file->ruta_dcm === 'processing') {
            if ($ext === 'zip') {
                // Return a signed URL so dwv can load the ZIP directly in the browser.
                // dwv 0.36 bundles JSZip and can extract DICOM files from a ZIP URL.
                try {
                    $zipUrl = Storage::disk('s3')->temporaryUrl($file->ruta, now()->addHours(2));
                    return response()->json(['count' => 0, 'type' => 'zip_url', 'url' => $zipUrl]);
                } catch (\Throwable) {
                    // Fallback: no signed URL support (local/Minio) — serve via PHP proxy
                    return response()->json(['count' => 0, 'type' => 'zip_proxy', 'proxy_url' => "/archivos/{$id}/" . basename($file->ruta)]);
                }
            }
            return response()->json(['count' => 1, 'type' => 'single']);
        }

        $paths = $this->seriePaths($id, $file->ruta_dcm);

        // Return signed S3 URLs so the browser fetches slices directly from S3
        // (bypasses the PHP proxy — eliminates the single-process bottleneck).
        try {
            $urls = array_map(
                fn ($p) => Storage::disk('s3')->temporaryUrl($p, now()->addHours(2)),
                $paths
            );
            return response()->json(['count' => count($paths), 'type' => 'serie', 'urls' => array_values($urls)]);
        } catch (\Throwable) {
            // Fallback: S3 driver doesn't support signed URLs (e.g. local Minio)
            return response()->json(['count' => count($paths), 'type' => 'serie']);
        }
    }

    /**
     * Serve the nth DICOM slice from a CBCT serie (0-based index).
     */
    public function serveSlice(int $id, int $index): StreamedResponse
    {
        $file = DB::table('files')->where('id', $id)->first(['ruta', 'ruta_dcm', 'extension']);
        abort_if(!$file, 404);

        if (!$file->ruta_dcm) {
            $ext = strtolower($file->extension ?? pathinfo((string) $file->ruta, PATHINFO_EXTENSION));
            abort_if($ext === 'zip', 422);
            abort_if($index !== 0, 404);
            return $this->streamS3Path($file->ruta);
        }

        $paths = $this->seriePaths($id, $file->ruta_dcm);
        abort_if(!isset($paths[$index]), 404);

        return $this->streamS3Path($paths[$index]);
    }

    /** List + sort DCM paths in an S3 prefix, cached for 1 hour. */
    private function seriePaths(int $fileId, string $prefix): array
    {
        return Cache::remember("serie_paths_{$fileId}", 3600, function () use ($prefix) {
            $all = Storage::disk('s3')->allFiles($prefix);
            $dcm = array_filter($all, fn ($p) => in_array(
                strtolower(pathinfo($p, PATHINFO_EXTENSION)),
                ['dcm', 'dicom'],
                true
            ));
            sort($dcm);
            return array_values($dcm);
        });
    }

    /**
     * Serve a DCM file or file_list.txt for a CBCT serie by filename.
     * med3web fetches the first DCM URL then looks for file_list.txt in the
     * same directory to discover all slices.
     */
    public function serveDcm(int $id, string $filename)
    {
        $file = DB::table('files')->where('id', $id)->first(['ruta', 'ruta_dcm']);
        abort_if(!$file || !$file->ruta_dcm, 404);

        $prefix = rtrim($file->ruta_dcm, '/') . '/';

        // Generate file_list.txt dynamically — no S3 write needed, avoids permission issues.
        if ($filename === 'file_list.txt') {
            try {
                $paths = $this->seriePaths($id, $file->ruta_dcm);
            } catch (\Throwable $e) {
                abort(500, 'Error listing serie: ' . $e->getMessage());
            }

            $names   = array_map('basename', $paths);
            sort($names);
            // Encode filenames so med3web constructs valid URLs (spaces → %20)
            $encoded = array_map('rawurlencode', $names);
            $content = implode("\n", $encoded);

            return response($content, 200, [
                'Content-Type'  => 'text/plain',
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        // Individual DCM: redirect to a signed S3 URL so the browser fetches
        // directly from S3 (avoids the single-process PHP proxy bottleneck).
        $dcmPath = $prefix . $filename;
        try {
            $signedUrl = Storage::disk('s3')->temporaryUrl($dcmPath, now()->addHours(2));
            return redirect()->to($signedUrl);
        } catch (\Throwable $e) {
            \Log::warning("serveDcm: signed URL failed for [{$dcmPath}]: {$e->getMessage()}");
        }
        return $this->streamS3Path($dcmPath, 'application/dicom');
    }

    private function streamS3Path(string $s3Path, string $mime = 'application/dicom'): StreamedResponse
    {
        try {
            $stream = Storage::disk('s3')->readStream($s3Path);
        } catch (\Throwable $e) {
            \Log::error("streamS3Path: failed to read [{$s3Path}]: {$e->getMessage()}");
            abort(404);
        }

        abort_if(!is_resource($stream), 404);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) fclose($stream);
        }, 200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Extract DCM files from a ZIP already on S3 and update the DB record.
     * Uses extractTo() + stream upload to avoid loading DCMs into PHP memory.
     */
    public function extractSerie(int $id): JsonResponse
    {
        $file = DB::table('files')->where('id', $id)->first(['id', 'ruta', 'extension', 'ruta_dcm', 'examination_id']);
        abort_if(!$file, 404);

        if ($file->ruta_dcm && $file->ruta_dcm !== 'processing') {
            return response()->json(['ok' => true, 'message' => 'Ya procesado.']);
        }

        $ext = strtolower($file->extension ?: pathinfo($file->ruta, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            return response()->json(['ok' => false, 'message' => 'No es un archivo ZIP.'], 422);
        }

        $eoRow   = DB::table('examination_order')->where('examination_id', $file->examination_id)->first(['order_id']);
        $orderId = $eoRow ? (int) $eoRow->order_id : 0;
        if (!$orderId) {
            return response()->json(['ok' => false, 'message' => 'No se pudo determinar la orden.'], 422);
        }

        set_time_limit(600);
        ignore_user_abort(true);

        // Preserve original ZIP path before ruta changes
        DB::table('files')->where('id', $id)->update(['nombre_dcm' => $file->ruta]);

        $tmpZip      = tempnam(sys_get_temp_dir(), 'cbct_') . '.zip';
        $tmpDir      = sys_get_temp_dir() . '/cbct_' . uniqid();
        $firstDcmS3  = null;
        $seriePrefix = null;
        $count       = 0;

        try {
            $stream = Storage::disk('s3')->readStream($file->ruta);
            if (!is_resource($stream)) {
                return response()->json(['ok' => false, 'message' => 'No se pudo leer el archivo desde S3.'], 500);
            }
            $fp = fopen($tmpZip, 'wb');
            stream_copy_to_stream($stream, $fp);
            fclose($fp);
            if (is_resource($stream)) fclose($stream);

            $za = new \ZipArchive();
            if ($za->open($tmpZip) !== true) {
                return response()->json(['ok' => false, 'message' => 'No se pudo abrir el ZIP.'], 422);
            }

            @mkdir($tmpDir, 0755, true);
            $za->extractTo($tmpDir);
            $za->close();
            unset($za);

            $tmpDirBase = rtrim(str_replace('\\', '/', $tmpDir), '/');
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iter as $localFile) {
                if (!$localFile->isFile()) continue;
                $entryExt = strtolower($localFile->getExtension());
                if (!in_array($entryExt, ['dcm', 'dicom'], true)) continue;

                $relPath = ltrim(
                    substr(str_replace('\\', '/', $localFile->getPathname()), strlen($tmpDirBase)),
                    '/'
                );
                $s3Path = "ordenes/{$orderId}/{$relPath}";

                $handle = fopen($localFile->getPathname(), 'rb');
                Storage::disk('s3')->put($s3Path, $handle);
                if (is_resource($handle)) fclose($handle);
                $count++;

                if ($firstDcmS3 === null) {
                    $firstDcmS3  = $s3Path;
                    $dir         = dirname($relPath);
                    $seriePrefix = "ordenes/{$orderId}/" . ($dir === '.' ? '' : rtrim($dir, '/') . '/');
                }
            }
        } finally {
            @unlink($tmpZip);
            $this->rrmdir($tmpDir);
        }

        if ($firstDcmS3 === null) {
            return response()->json(['ok' => false, 'message' => 'El ZIP no contiene archivos DICOM (.dcm).'], 422);
        }

        DB::table('files')->where('id', $id)->update([
            'ruta'       => $firstDcmS3,
            'ruta_dcm'   => $seriePrefix,
            'extension'  => 'dcm',
            'updated_at' => now(),
        ]);

        Cache::forget("serie_paths_{$id}");

        return response()->json(['ok' => true, 'count' => $count, 'message' => "{$count} archivos DICOM procesados."]);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff((array) scandir($dir), ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Return a presigned S3 PUT URL so the browser can upload the CBCT ZIP
     * directly to S3 without going through PHP.
     * Requires the S3 bucket to have CORS configured for PUT from the app origin.
     */
    public function cbctPresignedPut(\Illuminate\Http\Request $request): JsonResponse
    {
        $filename = $request->query('filename', 'cbct.zip');
        $safeName = preg_replace('/[^a-zA-Z0-9._()-]/', '_', basename($filename));
        $tempPath = 'cbct-temp/' . \Illuminate\Support\Str::uuid() . '/' . $safeName;

        try {
            $s3 = new \Aws\S3\S3Client([
                'version'     => 'latest',
                'region'      => config('filesystems.disks.s3.region', 'us-east-1'),
                'credentials' => [
                    'key'    => config('filesystems.disks.s3.key'),
                    'secret' => config('filesystems.disks.s3.secret'),
                ],
            ]);

            $cmd       = $s3->getCommand('PutObject', [
                'Bucket'      => config('filesystems.disks.s3.bucket'),
                'Key'         => $tempPath,
                'ContentType' => 'application/zip',
            ]);
            $presigned = $s3->createPresignedRequest($cmd, '+30 minutes');
            $url       = (string) $presigned->getUri();

            return response()->json([
                'url'      => $url,
                'headers'  => [],
                's3_path'  => $tempPath,
                'filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('CBCT_PRESIGNED_FAIL', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'presigned_not_supported'], 422);
        }
    }

    /**
     * Upload a CBCT ZIP to a temporary S3 path and return the path + metadata.
     * Fallback when presigned PUT is not supported or CORS is not configured.
     */
    public function uploadCbctTemp(\Illuminate\Http\Request $request): JsonResponse
    {
        $file = $request->file('file');

        if (!$file) {
            $phpErr = $_FILES['file']['error'] ?? 'key_missing';
            \Log::warning('CBCT_UPLOAD_NO_FILE', [
                'php_upload_error'    => $phpErr,
                'content_length'      => $request->header('Content-Length'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size'       => ini_get('post_max_size'),
            ]);
            abort(422, "No file provided (php_err={$phpErr})");
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            abort(422, 'Solo archivos ZIP (ext=' . $file->getClientOriginalExtension() . ')');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._()-]/', '_', $file->getClientOriginalName());
        $tempPath = 'cbct-temp/' . Str::uuid() . '/' . $safeName;

        $stream = fopen($file->getRealPath(), 'rb');
        Storage::disk('s3')->put($tempPath, $stream);
        if (is_resource($stream)) fclose($stream);

        // Iniciar extracción de DCMs en background mientras el usuario llena el form
        $uuid = explode('/', $tempPath)[1] ?? '';
        if ($uuid) {
            ProcessCbctZipTemp::dispatch($tempPath, $uuid)
                ->onConnection('database')->onQueue('default');
        }

        return response()->json([
            's3_path'   => $tempPath,
            'filename'  => $file->getClientOriginalName(),
            'file_size' => (int) $file->getSize(),
        ]);
    }

    public function startPreprocess(\Illuminate\Http\Request $request): JsonResponse
    {
        $s3Path = $request->input('s3_path', '');
        if (preg_match('#^cbct-temp/([^/]+)/#', $s3Path, $m)) {
            $uuid = $m[1];
            if (! Cache::has("cbct_preprocess_dispatched_{$uuid}")) {
                ProcessCbctZipTemp::dispatch($s3Path, $uuid)
                    ->onConnection('database')->onQueue('default');
                Cache::put("cbct_preprocess_dispatched_{$uuid}", true, now()->addHours(4));
            }
        }
        return response()->json(['ok' => true]);
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
            'dcm', 'dicom'=> 'application/dicom',
            default       => 'application/octet-stream',
        };
    }
}
