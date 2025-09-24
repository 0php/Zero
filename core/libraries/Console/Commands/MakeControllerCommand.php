<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Support\Str;
use Zero\Lib\Template;

final class MakeControllerCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Generate a controller class';
    }

    public function getUsage(): string
    {
        return 'php zero make:controller Name [--force]';
    }

    public function execute(array $argv): int
    {
        $name = $argv[2] ?? null;
        $force = in_array('--force', $argv, true);

        if ($name === null) {
            \Zero\Lib\Log::channel('internal')->error("Usage: {$this->getUsage()}");

            return 1;
        }

        // Convert namespace separators to directory separators
        $name = str_replace('\\', '/', $name);
        $className = Str::ensureSuffix(Str::studly(basename($name)), 'Controller');
        
        // Get the directory path from the name
        $directory = dirname($name);
        $directory = $directory === '.' ? '' : $directory . '/';
        
        // Build the full path
        $path = app_path('controllers/' . $directory . $className . '.php');

        if (file_exists($path) && !$force) {
            \Zero\Lib\Log::channel('internal')->error("Controller {$className} already exists. Use --force to overwrite.");
            return 1;
        }

        // Ensure the target directory exists
        Filesystem::ensureDirectory(dirname($path));

        // Generate namespace based on the directory structure
        $namespace = 'App\\Controllers';
        if ($directory !== '') {
            $namespace .= '\\' . str_replace('/', '\\', rtrim($directory, '/'));
        }

        $contents = Template::render('controller.tmpl', [
            'class' => $className,
            'namespace' => $namespace,
        ]);

        file_put_contents($path, $contents);
        \Zero\Lib\Log::channel('internal')->info("Controller created: {$path}");

        return 0;
    }
}
