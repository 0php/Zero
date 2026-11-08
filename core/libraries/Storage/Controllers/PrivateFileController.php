<?php

declare(strict_types=1);

namespace Zero\Lib\Storage\Controllers;

use Zero\Lib\Filesystem\MimeTypeMap;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Storage\Storage;

final class PrivateFileController
{
    private const DISK = 'private';

    public function __invoke(Request $request, string $path): Response
    {
        $normalized = ltrim($path, '/');

        if ($normalized === '') {
            return Response::make('Not Found', 404);
        }

        // Reject path traversal before any filesystem access. The route
        // segment {path:.+} matches "/" and "..", and the signature
        // middleware validates the ?path= query value (not this segment),
        // so containment must be enforced here too. A NUL byte or a ".."
        // component is always rejected.
        if (
            str_contains($normalized, "\0")
            || preg_match('#(^|/)\.\.(/|$)#', $normalized) === 1
        ) {
            return Response::make('Not Found', 404);
        }

        // The served value MUST equal the value the signature was issued for.
        // Temporary URLs embed the key in both the route segment and the
        // signed ?path= query; if a caller supplies a different ?path= (to
        // satisfy the signature check) than the segment it is serving,
        // refuse — this closes the segment/query decoupling.
        $signedPath = $request->input('path');
        if (is_string($signedPath) && ltrim($signedPath, '/') !== $normalized) {
            return Response::make('Not Found', 404);
        }

        if (! Storage::disk(self::DISK)->exists($normalized)) {
            return Response::make('Not Found', 404);
        }

        $disposition = $request->input('download') || $this->isActiveContent($normalized) ? 'attachment' : 'inline';

        return Storage::response($normalized, self::DISK, [
            'disposition' => $disposition,
            'headers' => [
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
                // Even if a browser renders the file, it cannot run script on the app origin.
                'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox",
            ],
        ]);
    }

    /**
     * HTML, SVG, XML and script files are always downloaded instead of rendered inline (stored XSS).
     */
    private function isActiveContent(string $path): bool
    {
        if (MimeTypeMap::isActiveContent((string) pathinfo($path, PATHINFO_EXTENSION))) {
            return true;
        }

        try {
            $mime = strtolower((string) Storage::disk(self::DISK)->mimeType($path));
        } catch (\Throwable) {
            return false;
        }

        return str_contains($mime, 'html') || str_contains($mime, 'svg') || str_contains($mime, 'xml') || str_contains($mime, 'javascript');
    }
}
