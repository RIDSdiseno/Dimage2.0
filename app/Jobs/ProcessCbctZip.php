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

        $updated = false;
        try {
            $stream = Storage::disk('s3')->readStream($this->zipS3Path);
            if (! is_resource($stream)) return;

            $fp = fopen($tmpZip, 'wb');
            stream_copy_to_stream($stream, $fp);
            fclose($fp);
            if (is_resource($stream)) fclose($stream);

            $za = new \ZipArchive();
            if ($za->open($tmpZip) !== true) return;

            @mkdir($tmpDir, 0755, true);
            $za->extractTo($tmpDir);
            $za->close();
            unset($za);

            $firstDcmS3  = null;
            $seriePrefix = null;
            $tmpDirBase  = rtrim(str_replace('\\', '/', $tmpDir), '/');

            // Collect DCM files to upload
            $uploads = []; // [ [s3Key, localPath] ]
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iter as $file) {
                if (! $file->isFile()) continue;
                $entryExt = strtolower($file->getExtension());
                // Accept .dcm, .dicom, and extensionless files (DICOM standard allows no extension)
                if ($entryExt !== '' && ! in_array($entryExt, ['dcm', 'dicom'], true)) continue;

                $relPath = ltrim(
                    substr(str_replace('\\', '/', $file->getPathname()), strlen($tmpDirBase)),
                    '/'
                );
                $s3Path = "ordenes/{$this->orderId}/{$relPath}";
                $uploads[] = [$s3Path, $file->getPathname()];

                if ($firstDcmS3 === null) {
                    $firstDcmS3  = $s3Path;
                    $dir         = dirname($relPath);
                    $seriePrefix = "ordenes/{$this->orderId}/" . ($dir === '.' ? '' : rtrim($dir, '/') . '/');
                }
            }

            // Upload all DCMs in parallel batches of 20 (20x faster than sequential)
            $this->parallelUpload($uploads);

            if ($firstDcmS3) {
                DB::table('files')->where('id', $this->fileId)->update([
                    'ruta'       => $firstDcmS3,
                    'ruta_dcm'   => $seriePrefix,
                    'extension'  => 'dcm',
                    'updated_at' => now(),
                ]);
            } else {
                // ZIP has no DCM files — clear processing state, serve as download only
                DB::table('files')->where('id', $this->fileId)->update([
                    'ruta_dcm'   => null,
                    'updated_at' => now(),
                ]);
            }
            $updated = true;

        } finally {
            // If we returned early (S3 file missing, invalid ZIP), clear the processing state
            if (! $updated) {
                DB::table('files')->where('id', $this->fileId)->update([
                    'ruta_dcm'   => null,
                    'updated_at' => now(),
                ]);
            }
            @unlink($tmpZip);
            $this->rrmdir($tmpDir);
        }
    }

    /**
     * Upload files to S3 in parallel batches using CommandPool.
     * Creates the S3Client directly from config to avoid Flysystem adapter
     * version incompatibilities (getClient() not available in all versions).
     *
     * @param array $uploads  Array of [s3Key, localPath] pairs
     */
    private function parallelUpload(array $uploads, int $concurrency = 20): void
    {
        if (empty($uploads)) return;

        $cfg = [
            'version'                 => 'latest',
            'region'                  => config('filesystems.disks.s3.region'),
            'credentials'             => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
            'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint', false),
        ];
        $endpoint = config('filesystems.disks.s3.endpoint');
        if ($endpoint) $cfg['endpoint'] = $endpoint;

        $s3Client = new \Aws\S3\S3Client($cfg);
        $bucket   = config('filesystems.disks.s3.bucket');

        foreach (array_chunk($uploads, $concurrency) as $batch) {
            $commands = [];
            $handles  = [];

            foreach ($batch as [$s3Key, $localPath]) {
                $handle     = fopen($localPath, 'rb');
                $handles[]  = $handle;
                $commands[] = $s3Client->getCommand('PutObject', [
                    'Bucket' => $bucket,
                    'Key'    => $s3Key,
                    'Body'   => $handle,
                ]);
            }

            (new \Aws\CommandPool($s3Client, $commands, [
                'concurrency' => $concurrency,
            ]))->promise()->wait();

            foreach ($handles as $handle) {
                if (is_resource($handle)) fclose($handle);
            }
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
