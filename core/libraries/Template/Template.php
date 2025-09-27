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
     * Render a template replacing `{{ placeholder }}` tokens with bound data.
     */
    public static function render(string $name, array $data = []): string
    {
        $template = self::load($name);

        $template = preg_replace_callback('/{{{(.*?)}}}/s', function (array $matches) use ($data) {
            return self::replacePlaceholder($matches[0], $matches[1], $data);
        }, $template);

        $template = preg_replace_callback('/{!!\s*(.+?)\s*!!}/s', function (array $matches) use ($data) {
            return self::replacePlaceholder($matches[0], $matches[1], $data);
        }, $template);

        $template = preg_replace_callback('/{{\s*(.+?)\s*}}/', function (array $matches) use ($data) {
            return self::replacePlaceholder($matches[0], $matches[1], $data);
        }, $template);

        return $template;
    }

    private static function replacePlaceholder(string $placeholder, string $expression, array $data): string
    {
        $found = false;
        $value = self::extractValue($data, trim($expression), $found);

        if (! $found) {
            return $placeholder;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $value = (string) $value;
            } else {
                return $placeholder;
            }
        }

        if (is_array($value)) {
            return $placeholder;
        }

        return (string) $value;
    }

    private static function extractValue(array $data, string $expression, bool &$found): mixed
    {
        $found = false;

        if ($expression === '') {
            return '';
        }

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $expression)) {
            return null;
        }

        $segments = explode('.', $expression);
        $value = $data;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            if (is_object($value) && isset($value->{$segment})) {
                $value = $value->{$segment};
                continue;
            }

            return null;
        }

        $found = true;

        return $value;
    }
}
