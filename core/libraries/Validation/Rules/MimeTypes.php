<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Filesystem\File;
use Zero\Lib\Http\UploadedFile;
use Zero\Lib\Validation\RuleInterface;

final class MimeTypes implements RuleInterface
{
    /**
     * @param array<int, string> $types
     */
    public function __construct(private array $types)
    {
        $this->types = array_values(array_unique(array_map('strtolower', $this->types)));
    }

    public function name(): string
    {
        return 'mimetypes';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (! $value instanceof File) {
            return false;
        }

        $mime = $this->resolveMimeType($value);

        if ($mime === null) {
            return false;
        }

        foreach ($this->types as $type) {
            if ($type === '*') {
                return true;
            }

            if ($type === $mime) {
                return true;
            }

            if (str_ends_with($type, '/*')) {
                $prefix = substr($type, 0, -1); // retain trailing slash

                if (str_starts_with($mime, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function message(): string
    {
        return 'The :attribute must be a file of type: :types.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return ['types' => implode(', ', $this->types)];
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
