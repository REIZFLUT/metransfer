<?php

namespace App\Support;

use App\Models\Transfer;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DiskSpace
{
    /**
     * Free and total space of the filesystem that holds the transfer files,
     * or null if it cannot be determined (e.g. a remote disk like S3).
     *
     * @return array{free: int, total: int}|null
     */
    public static function forTransfers(): ?array
    {
        try {
            $path = Storage::disk(Transfer::disk())->path('');
        } catch (Throwable) {
            return null;
        }

        // The root directory may not exist yet on a fresh install.
        while (! is_dir($path) && dirname($path) !== $path) {
            $path = dirname($path);
        }

        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return null;
        }

        return ['free' => (int) $free, 'total' => (int) $total];
    }
}
