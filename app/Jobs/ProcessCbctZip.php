<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessCbctZip implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600; // 10 minutes max for large CBCT series

    public function __construct(
        public int    $fileId,
        public int    $orderId,
        public string $zipS3Path,
    ) {}

    public function handle(): void
    {
        // Preserve original ZIP path so download() can serve the full CBCT ZIP
        DB::table('files')->where('id', $this->fileId)->update([
            'nombre_dcm' => $this->zipS3Path,
        ]);

        $tmpZip = tempnam(sys_get_temp_dir(), 'cbct_') . '.zip';
        $tmpDir = sys_get_temp_dir() . '/cbct_' . uniqid();

        try {
            // Download ZIP from S3 to local temp file
            $stream = Storage::disk('s3')->readStream($this->zipS3Path);
            if (! is_resource($stream)) return;

            $fp = fopen($tmpZip, 'wb');
            stream_copy_to_stream($stream, $fp);
            fclose($fp);
            if (is_resource($stream)) fclose($stream);

            $za = new \ZipArchive();
            if ($za->open($tmpZip) !== true) return;

            // Extract everything to local disk first (fast local operation,
            // avoids loading each DCM into PHP memory string)
            @mkdir($tmpDir, 0755, true);
            $za->extractTo($tmpDir);
            $za->close();
            unset($za);

            $firstDcmS3  = null;
            $seriePrefix = null;
            $tmpDirBase  = rtrim(str_replace('\\', '/', $tmpDir), '/');

            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iter as $file) {
                if (! $file->isFile()) continue;

                $entryExt = strtolower($file->getExtension());
                if (! in_array($entryExt, ['dcm', 'dicom'], true)) continue;

                $relPath = ltrim(
                    substr(str_replace('\\', '/', $file->getPathname()), strlen($tmpDirBase)),
                    '/'
                );

                $s3Path = "ordenes/{$this->orderId}/{$relPath}";

                // Stream-upload from extracted temp file — no full-file memory allocation
                $handle = fopen($file->getPathname(), 'rb');
                Storage::disk('s3')->put($s3Path, $handle);
                if (is_resource($handle)) fclose($handle);

                if ($firstDcmS3 === null) {
                    $firstDcmS3  = $s3Path;
                    $dir         = dirname($relPath);
                    $seriePrefix = "ordenes/{$this->orderId}/" . ($dir === '.' ? '' : rtrim($dir, '/') . '/');
                }
            }

            if ($firstDcmS3) {
                DB::table('files')->where('id', $this->fileId)->update([
                    'ruta'       => $firstDcmS3,
                    'ruta_dcm'   => $seriePrefix,
                    'extension'  => 'dcm',
                    'updated_at' => now(),
                ]);
            }

        } finally {
            @unlink($tmpZip);
            $this->rrmdir($tmpDir);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) return;
        foreach (array_diff((array) scandir($dir), ['.', '..']) as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function failed(\Throwable $e): void
    {
        // Mark as failed so the viewer can show an error state
        DB::table('files')->where('id', $this->fileId)->update([
            'ruta_dcm'   => null,
            'extension'  => 'zip_error',
            'updated_at' => now(),
        ]);
    }
}
