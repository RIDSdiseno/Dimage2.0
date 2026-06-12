<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Extracts DCMs from an eagerly-uploaded CBCT ZIP into cbct-temp/{uuid}/dcm/
 * and stores the result in cache so OrderController::store() can use it
 * without having to re-extract on form submit.
 *
 * If the user submits before this job finishes, store() falls back to async
 * extraction via ProcessCbctZip.
 */
class ProcessCbctZipTemp implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $zipS3Path,
        public string $uuid,
    ) {}

    public function handle(): void
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'cbct_t_') . '.zip';
        $tmpDir = sys_get_temp_dir() . '/cbct_t_' . uniqid();

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

            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iter as $localFile) {
                if (! $localFile->isFile()) continue;
                $entryExt = strtolower($localFile->getExtension());
                if (! in_array($entryExt, ['dcm', 'dicom'], true)) continue;

                $relPath = ltrim(
                    substr(str_replace('\\', '/', $localFile->getPathname()), strlen($tmpDirBase)),
                    '/'
                );
                $s3Path = "cbct-temp/{$this->uuid}/dcm/{$relPath}";

                $handle = fopen($localFile->getPathname(), 'rb');
                Storage::disk('s3')->put($s3Path, $handle);
                if (is_resource($handle)) fclose($handle);

                if ($firstDcmS3 === null) {
                    $firstDcmS3  = $s3Path;
                    $dir         = dirname($relPath);
                    $seriePrefix = "cbct-temp/{$this->uuid}/dcm/" . ($dir === '.' ? '' : rtrim($dir, '/') . '/');
                }
            }

            if ($firstDcmS3) {
                // Cache for 4 hours — long enough for any form-filling session
                Cache::put("cbct_preprocess_{$this->uuid}", [
                    'first_dcm' => $firstDcmS3,
                    'prefix'    => $seriePrefix,
                ], now()->addHours(4));
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
}
