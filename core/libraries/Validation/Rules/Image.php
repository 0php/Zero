<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Filesystem\File;
use Zero\Lib\Http\UploadedFile;
use Zero\Lib\Validation\RuleInterface;

final class Image implements RuleInterface
{
    public function name(): string
    {
        return 'image';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (! $value instanceof File) {
            return false;
        }

        if ($value instanceof UploadedFile && ! $value->isValid()) {
            return false;
        }

        $mime = $this->resolveMimeType($value);

        return $mime !== null && str_starts_with($mime, 'image/');
    }

    public function message(): string
    {
        return 'The :attribute must be an image file.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }

    private function resolveMimeType(File $file): ?string
    {
        if ($file instanceof UploadedFile) {
            $clientMime = strtolower((string) $file->getClientMimeType());

            if ($clientMime !== '') {
                return $clientMime;
            }
        }

        try {
            $detected = strtolower((string) $file->getMimeType());
        } catch (\Throwable) {
            $detected = '';
        }

        return $detected !== '' ? $detected : null;
    }
}
