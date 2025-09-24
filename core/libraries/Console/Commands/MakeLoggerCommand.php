<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Support\Str;
use Zero\Lib\Template;

final class MakeLoggerCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:logger';
    }

    public function getDescription(): string
    {
        return 'Generate a custom log handler and register its channel';
    }

    public function getUsage(): string
    {
        return 'php zero make:logger Name [--force]';
    }

    public function execute(array $argv): int
    {
        $name = $argv[2] ?? null;
        $force = in_array('--force', $argv, true);

        if ($name === null) {
            \Zero\Lib\Log::channel('internal')->error("Usage: {$this->getUsage()}");

            return 1;
        }

        $name = str_replace('\\', '/', $name);
        $name = preg_replace('#/+#', '/', $name);
        $name = trim((string) $name, '/');

        if ($name === '') {
            \Zero\Lib\Log::channel('internal')->error("Usage: {$this->getUsage()}");

            return 1;
        }

        $className = Str::studly(basename($name));

        $directory = dirname($name);
        $directory = $directory === '.' ? '' : $directory . '/';

        $namespace = 'App\\Logging';
        if ($directory !== '') {
            $namespace .= '\\' . str_replace('/', '\\', rtrim($directory, '/'));
        }

        $path = app_path('logging/' . $directory . $className . '.php');

        if (file_exists($path) && ! $force) {
            \Zero\Lib\Log::channel('internal')->error("Log handler {$className} already exists. Use --force to overwrite.");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $contents = Template::render('log-handler.tmpl', [
            'namespace' => $namespace,
            'class' => $className,
        ]);

        file_put_contents($path, $contents);

        $keySource = trim($name) !== '' ? str_replace('/', ' ', $name) : $className;
        $channelKey = Str::snake($keySource);
        $fullyQualified = $namespace . '\\' . $className;
        $registered = $this->registerChannel($channelKey, $fullyQualified);

        \Zero\Lib\Log::channel('internal')->info("Log handler created: {$path}");
        if ($registered) {
            \Zero\Lib\Log::channel('internal')->info("Channel added to config/logging.php as '{$channelKey}'.");
        } else {
            \Zero\Lib\Log::channel('internal')->error('Unable to update config/logging.php automatically. Please add the channel manually.');
        }

        return 0;
    }

    private function registerChannel(string $channelKey, string $handler): bool
    {
        $configPath = config_path('logging.php');

        if (! file_exists($configPath) || ! is_readable($configPath)) {
            return false;
        }

        $contents = file_get_contents($configPath);
        if ($contents === false) {
            return false;
        }

        if (str_contains($contents, "'{$channelKey}' =>")) {
            return true;
        }

        $handlerReference = $handler . '::class';
        $block = "        '{$channelKey}' => [\n"
            . "            'driver' => 'custom',\n"
            . "            'handler' => {$handlerReference},\n"
            . "        ],\n";

        $updated = preg_replace(
            "/(\n\s*'stack'\s*=>\s*\[)/",
            "\n" . $block . "$1",
            $contents,
            1
        );

        if ($updated === null) {
            return false;
        }

        if ($updated === $contents) {
            $updated = preg_replace(
                "/(\n\s*\],\n\];\n)/",
                "\n" . $block . "$1",
                $contents,
                1
            );

            if ($updated === null || $updated === $contents) {
                return false;
            }
        }

        file_put_contents($configPath, $updated);

        return true;
    }
}
