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
        // Download ZIP from S3 to a local temp file
        $tmpZip = tempnam(sys_get_temp_dir(), 'cbct_') . '.zip';

        try {
            $stream = Storage::disk('s3')->readStream($this->zipS3Path);
            if (! is_resource($stream)) {
                return;
            }
            $fp = fopen($tmpZip, 'wb');
            stream_copy_to_stream($stream, $fp);
            fclose($fp);
            if (is_resource($stream)) fclose($stream);

            $za = new \ZipArchive();
            if ($za->open($tmpZip) !== true) {
                return;
            }

            $firstDcmS3  = null;
            $seriePrefix = null;

            for ($i = 0; $i < $za->numFiles; $i++) {
                $entry = $za->getNameIndex($i);
                if (! $entry || str_ends_with($entry, '/')) {
                    continue;
                }

                $entryExt = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (! in_array($entryExt, ['dcm', 'dicom'], true)) {
                    continue;
                }

                $content = $za->getFromIndex($i);
                if ($content === false) {
                    continue;
                }

                $s3Path = "ordenes/{$this->orderId}/{$entry}";
                Storage::disk('s3')->put($s3Path, $content);

                if ($firstDcmS3 === null) {
                    $firstDcmS3  = $s3Path;
                    $dir         = dirname($entry);
                    $seriePrefix = "ordenes/{$this->orderId}/" . ($dir === '.' ? '' : rtrim($dir, '/') . '/');
                }
            }

            $za->close();

            if ($firstDcmS3) {
                DB::table('files')->where('id', $this->fileId)->update([
                    'ruta'      => $firstDcmS3,
                    'ruta_dcm'  => $seriePrefix,
                    'extension' => 'dcm',
                    'updated_at'=> now(),
                ]);

                // Remove the original ZIP from S3 to save space
                try {
                    Storage::disk('s3')->delete($this->zipS3Path);
                } catch (\Throwable) {}
            }
        } finally {
            @unlink($tmpZip);
        }
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
