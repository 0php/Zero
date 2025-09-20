<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Console\Support\Str;
use Zero\Lib\Template;

final class MakeMiddlewareCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:middleware';
    }

    public function getDescription(): string
    {
        return 'Generate a middleware class';
    }

    public function getUsage(): string
    {
        return 'php zero make:middleware Name [--force]';
    }

    public function execute(array $argv): int
    {
        $name = $argv[2] ?? null;
        $force = in_array('--force', $argv, true);

        if ($name === null) {
            fwrite(STDERR, "Usage: {$this->getUsage()}\n");

            return 1;
        }

        $className = Str::studly($name);
        $path = app_path('middlewares/' . $className . '.php');

        if (file_exists($path) && ! $force) {
            fwrite(STDERR, "Middleware {$className} already exists. Use --force to overwrite.\n");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $contents = Template::render('middleware.tmpl', [
            'class' => $className,
        ]);

        file_put_contents($path, $contents);
        fwrite(STDOUT, "Middleware created: {$path}\n");

        return 0;
    }
}
