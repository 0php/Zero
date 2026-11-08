<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Filesystem\File;
use Zero\Lib\Filesystem\MimeTypeMap;
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

        return array_intersect($this->resolveExtensions($value), $this->extensions) !== [];
    }

    public function message(): string
    {
        return 'The :attribute must be a file of type: :types.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return ['types' => implode(', ', $this->extensions)];
    }

    /**
     * Extensions the file may legitimately have, derived from its detected content. The client
     * extension only counts for generic/unknown content types and when it is harmless.
     *
     * @return string[]
     */
    private function resolveExtensions(File $file): array
    {
        $mime = MimeTypeMap::detect($file->getPath());
        $fromContent = MimeTypeMap::extensionsFor($mime);

        $extension = $file instanceof UploadedFile
            ? strtolower((string) $file->getClientExtension())
            : strtolower((string) $file->getExtension());

        // Generic content (plain text / unknown binary) may legitimately be .csv, .stl, .gcode...
        if (MimeTypeMap::isGeneric($mime) && $extension !== '' && MimeTypeMap::isHarmless($extension)) {
            return array_values(array_unique([...$fromContent, $extension]));
        }

        if ($fromContent !== []) {
            return $fromContent;
        }

        if ($extension === '' || !MimeTypeMap::isHarmless($extension)) {
            return [];
        }

        if ($mime !== null && (str_contains($mime, 'php') || str_contains($mime, 'html') || str_contains($mime, 'script'))) {
            return [];
        }

        return [$extension];
    }
}
