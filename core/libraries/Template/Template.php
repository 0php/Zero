<?php

declare(strict_types=1);

namespace Zero\Lib;

use RuntimeException;

class Template
{
    /**
     * Base directory for templates relative to the framework root.
     */
    protected static string $basePath = 'core/templates';

    /**
     * Load a template file and return its contents.
     */
    public static function load(string $name): string
    {
        $path = base(static::$basePath . '/' . trim($name, '/'));

        if (! file_exists($path)) {
            throw new RuntimeException("Template not found: {$path}");
        }

        return file_get_contents($path) ?: '';
    }

    /**
     * Render a template replacing {{ placeholders }} with bound data.
     */
    public static function render(string $name, array $data = []): string
    {
        $template = self::load($name);

        return preg_replace_callback('/{{\s*(.+?)\s*}}/', function ($matches) use ($data) {
            $key = $matches[1];

            return array_key_exists($key, $data) ? (string) $data[$key] : $matches[0];
        }, $template);
    }
}
