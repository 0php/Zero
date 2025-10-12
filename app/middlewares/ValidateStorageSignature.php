<?php

namespace App\Middlewares;

use RuntimeException;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Support\DateTime;

class ValidateStorageSignature
{
    public function handle(Request $request): ?Response
    {
        $expires = $this->extractExpiration($request);
        $signature = $this->extractSignature($request);

        if ($expires === null || $signature === null) {
            return $this->reject();
        }

        if ($expires->getTimestamp() < time()) {
            return $this->reject();
        }

        $resource = $this->resolveResourcePath($request);
        if ($resource === null) {
            return $this->reject();
        }

        if (!hash_equals($this->sign($resource, $expires), $signature)) {
            return $this->reject();
        }

        return null;
    }

    private function extractExpiration(Request $request): ?DateTime
    {
        $value = $request->input('expires');

        if ($value === null || $value === '') {
            return null;
        }

        if (!ctype_digit((string) $value)) {
            return null;
        }

        return DateTime::parse('@' . $value);
    }

    private function extractSignature(Request $request): ?string
    {
        $signature = $request->input('signature');

        return is_string($signature) && $signature !== '' ? $signature : null;
    }

    private function resolveResourcePath(Request $request): ?string
    {
        $explicit = $request->input('path');
        if (is_string($explicit) && $explicit !== '') {
            return ltrim($explicit, '/');
        }

        $attribute = $request->attribute('storage.signed.path');
        if (is_string($attribute) && $attribute !== '') {
            return ltrim($attribute, '/');
        }

        $path = trim($request->path(), '/');

        return $path !== '' ? $path : null;
    }

    private function sign(string $resource, DateTime $expires): string
    {
        return hash_hmac('sha256', $resource . '|' . $expires->getTimestamp(), $this->signingKey());
    }

    private function signingKey(): string
    {
        $key = env('APP_KEY');

        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException('Temporary URL validation requires a valid APP_KEY.');
        }

        return $key;
    }

    private function reject(): Response
    {
        return Response::json([
            'message' => 'The temporary link is invalid or has expired.',
        ], 403);
    }
}
