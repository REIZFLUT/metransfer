<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Responses for user-uploaded files. Uploaded content is never trusted:
 * it is always sent as an opaque attachment so a browser cannot render or
 * execute it in the context of this site (e.g. HTML/SVG with JavaScript).
 */
class SecureDownload
{
    /**
     * Headers that prevent the browser from interpreting downloaded content.
     *
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control' => 'private, no-store',
        ];
    }

    public static function file(string $path, string $name): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => 'application/octet-stream',
            ...static::headers(),
        ], public: false, autoEtag: false, autoLastModified: true);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $name,
            static::asciiFallback($name),
        );

        return $response;
    }

    protected static function asciiFallback(string $name): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]|[%\/\\\\"]/', '_', $name) ?? 'download';

        return $fallback !== '' ? $fallback : 'download';
    }
}
