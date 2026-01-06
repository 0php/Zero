<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Filesystem\File;
use Zero\Lib\Http\UploadedFile;
use Zero\Lib\Validation\RuleInterface;

final class Mimes implements RuleInterface
{
    /**
     * @param array<int, string> $extensions
     */
    public function __construct(private array $extensions)
    {
        $this->extensions = array_values(array_unique(array_map('strtolower', $this->extensions)));
    }

    public function name(): string
    {
        return 'mimes';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (! $value instanceof File) {
            return false;
        }

        $extension = $this->resolveExtension($value);

        return $extension !== null && in_array($extension, $this->extensions, true);
    }

    public function message(): string
    {
        return 'The :attribute must be a file of type: :types.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return ['types' => implode(', ', $this->extensions)];
    }

    private function resolveExtension(File $file): ?string
    {
        $extension = null;

        if ($file instanceof UploadedFile) {
            $clientExtension = strtolower((string) $file->getClientExtension());

            if ($clientExtension !== '') {
                $extension = $clientExtension;
            }
        }

        if ($extension === null) {
            $fileExtension = strtolower((string) $file->getExtension());

            if ($fileExtension !== '') {
                $extension = $fileExtension;
            }
        }

        return $extension;
    }
}
