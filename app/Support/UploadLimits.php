<?php

namespace App\Support;

class UploadLimits
{
    /**
     * The effective chunk size: the configured value, capped so that a
     * chunk always fits into PHP's post_max_size.
     */
    public static function chunkSize(): int
    {
        $configured = (int) config('transfers.chunk_size');
        $postMax = static::iniBytes((string) ini_get('post_max_size'));

        if ($postMax <= 0) {
            return $configured;
        }

        // Leave some headroom below the hard limit.
        $ceiling = max(256 * 1024, $postMax - 64 * 1024);

        return min($configured, $ceiling);
    }

    public static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
