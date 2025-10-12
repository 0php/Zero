<?php

declare(strict_types=1);

namespace Zero\Lib\Storage\Controllers;

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

        if (! Storage::disk(self::DISK)->exists($normalized)) {
            return Response::make('Not Found', 404);
        }

        $disposition = $request->input('download') ? 'attachment' : 'inline';

        return Storage::response($normalized, self::DISK, [
            'disposition' => $disposition,
            'headers' => [
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
        ]);
    }
}
