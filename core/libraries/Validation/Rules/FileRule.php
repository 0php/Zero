<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Filesystem\File;
use Zero\Lib\Http\UploadedFile;
use Zero\Lib\Validation\RuleInterface;

final class FileRule implements RuleInterface
{
    public function name(): string
    {
        return 'file';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (! $value instanceof File) {
            return false;
        }

        if ($value instanceof UploadedFile) {
            return $value->isValid();
        }

        return true;
    }

    public function message(): string
    {
        return 'The :attribute must be a valid file.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}
