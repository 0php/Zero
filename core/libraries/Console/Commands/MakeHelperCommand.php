<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
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

    public function execute(array $argv): int
    {
        $name = $argv[2] ?? null;
        $force = in_array('--force', $argv, true);

        if ($name === null) {
            fwrite(STDERR, "Usage: {$this->getUsage()}\n");

            return 1;
        }

        $name = str_replace('\\', '/', $name);
        $className = Str::studly(basename($name));

        if ($className === '') {
            fwrite(STDERR, "Invalid helper name provided.\n");

            return 1;
        }

        $directory = dirname($name);
        $directory = $directory === '.' ? '' : trim($directory, '/') . '/';

        $path = app_path('helpers/' . $directory . $className . '.php');

        if (file_exists($path) && ! $force) {
            fwrite(STDERR, "Helper {$className} already exists. Use --force to overwrite.\n");

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
        fwrite(STDOUT, "Helper created: {$path}\n");

        return 0;
    }
}
