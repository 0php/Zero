<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Support\Str;
use Zero\Lib\Template;

final class MakeServiceCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:service';
    }

    public function getDescription(): string
    {
        return 'Generate a service class';
    }

    public function getUsage(): string
    {
        return 'php zero make:service Name [--force]';
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
        $name = preg_replace('#/+#', '/', $name);
        $name = trim($name, '/');

        if ($name === '') {
            fwrite(STDERR, "Usage: {$this->getUsage()}\n");

            return 1;
        }

        $className = Str::studly(basename($name));

        $directory = dirname($name);
        $directory = $directory === '.' ? '' : $directory . '/';

        $path = app_path('services/' . $directory . $className . '.php');

        if (file_exists($path) && ! $force) {
            fwrite(STDERR, "Service {$className} already exists. Use --force to overwrite.\n");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $namespace = 'App\\Services';
        if ($directory !== '') {
            $namespace .= '\\' . str_replace('/', '\\', rtrim($directory, '/'));
        }

        $contents = Template::render('service.tmpl', [
            'class' => $className,
            'namespace' => $namespace,
        ]);

        file_put_contents($path, $contents);
        fwrite(STDOUT, "Service created: {$path}\n");

        return 0;
    }
}
