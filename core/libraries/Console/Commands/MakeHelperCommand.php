<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Input;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Support\Str;
use Zero\Lib\Template;

final class MakeHelperCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:helper';
    }

    public function getDescription(): string
    {
        return 'Generate a helper class';
    }

    public function getUsage(): string
    {
        return 'php zero make:helper Name [--force]';
    }

    public function execute(array $argv, ?Input $input = null): int
    {
        $input ??= new Input([], []);

        $arguments = $input->arguments();
        $name = $arguments[0] ?? $argv[2] ?? null;
        $force = (bool) ($input->option('force', false) || in_array('--force', $argv, true));

        if ($name === null) {
            \Zero\Lib\Log::channel('internal')->error("Usage: {$this->getUsage()}");

            return 1;
        }

        $name = str_replace('\\', '/', $name);
        $className = Str::studly(basename($name));

        if ($className === '') {
            \Zero\Lib\Log::channel('internal')->error('Invalid helper name provided.');

            return 1;
        }

        $directory = dirname($name);
        $directory = $directory === '.' ? '' : trim($directory, '/') . '/';

        $path = app_path('helpers/' . $directory . $className . '.php');

        if (file_exists($path) && ! $force) {
            \Zero\Lib\Log::channel('internal')->error("Helper {$className} already exists. Use --force to overwrite.");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $namespace = 'App\\Helpers';
        if ($directory !== '') {
            $namespace .= '\\' . str_replace('/', '\\', rtrim($directory, '/'));
        }

        $signature = Str::snake($className);

        $contents = Template::render('helper.tmpl', [
            'namespace' => $namespace,
            'class' => $className,
            'signature' => $signature,
        ]);

        file_put_contents($path, $contents);
        \Zero\Lib\Log::channel('internal')->info("Helper created: {$path}");

        $fullyQualified = '\\' . ltrim($namespace . '\\' . $className, '\\');
        if ($this->appendToHelperRegistry($fullyQualified)) {
            \Zero\Lib\Log::channel('internal')->info('Helper registered in app/helpers/Helper.php');
        } else {
            \Zero\Lib\Log::channel('internal')->error('Unable to update app/helpers/Helper.php automatically.');
        }

        return 0;
    }

    private function appendToHelperRegistry(string $class): bool
    {
        $helperPath = app_path('helpers/Helper.php');

        if (! file_exists($helperPath) || ! is_writable($helperPath)) {
            return false;
        }

        $contents = file_get_contents($helperPath);
        if ($contents === false) {
            return false;
        }

        if (str_contains($contents, $class . '::class')) {
            return true;
        }

        if (! preg_match('/\$this->register\(\s*\[\s*(.*?)\s*\]\);/s', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $block = $matches[1][0];
        $blockOffset = (int) $matches[1][1];

        $lines = preg_split('/\R/', trim($block)) ?: [];
        $entries = [];
        $comments = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '//')) {
                $comments[] = $trimmed;

                continue;
            }

            $entries[] = rtrim($trimmed, ',');
        }

        $entries[] = $class . '::class';
        $entries = array_unique($entries);
        $entries = array_values($entries);
        usort($entries, static function (string $a, string $b): int {
            $normalize = static fn (string $value): string => ltrim(preg_replace('/::class$/', '', $value) ?? $value, '\\');

            return strcmp($normalize($a), $normalize($b));
        });

        $indent = '            ';
        $rebuilt = [];
        foreach ($entries as $entry) {
            $rebuilt[] = $indent . $entry . ',';
        }

        foreach ($comments as $comment) {
            $rebuilt[] = $indent . $comment;
        }

        $replacement = PHP_EOL . implode(PHP_EOL, $rebuilt) . PHP_EOL . '        ';
        $updated = substr_replace($contents, $replacement, $blockOffset, strlen($block));
        $updated = preg_replace("/\n[ \t]+\n/", "\n", $updated) ?? $updated;

        return file_put_contents($helperPath, $updated) !== false;
    }
}
