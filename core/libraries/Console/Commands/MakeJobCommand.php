<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Support\Str;
use Zero\Lib\Template;

final class MakeJobCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:job';
    }

    public function getDescription(): string
    {
        return 'Generate a queueable job class';
    }

    public function getUsage(): string
    {
        return 'php zero make:job Name [--force]';
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
        $name = trim($name, '/');

        if ($name === '') {
            \Zero\Lib\Log::channel('internal')->error("Usage: {$this->getUsage()}");

            return 1;
        }

        $className = Str::studly(basename($name));
        $directory = dirname($name);
        $directory = $directory === '.' ? '' : $directory . '/';

        $path = app_path('jobs/' . $directory . $className . '.php');

        if (file_exists($path) && ! $force) {
            \Zero\Lib\Log::channel('internal')->error("Job {$className} already exists. Use --force to overwrite.");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $namespace = 'App\\Jobs';
        if ($directory !== '') {
            $namespace .= '\\' . str_replace('/', '\\', rtrim($directory, '/'));
        }

        $contents = Template::render('job.tmpl', [
            'class' => $className,
            'namespace' => $namespace,
        ]);

        file_put_contents($path, $contents);
        \Zero\Lib\Log::channel('internal')->info("Job created: {$path}");

        return 0;
    }
}
