<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Console\Support\Str;
use Zero\Lib\Template;

final class MakeModelCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:model';
    }

    public function getDescription(): string
    {
        return 'Generate an Eloquent-like model class';
    }

    public function getUsage(): string
    {
        return 'php zero make:model Name [--force]';
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
        $path = app_path('models/' . $className . '.php');

        if (file_exists($path) && ! $force) {
            fwrite(STDERR, "Model {$className} already exists. Use --force to overwrite.\n");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $contents = Template::render('model.tmpl', [
            'class' => $className,
        ]);

        file_put_contents($path, $contents);
        fwrite(STDOUT, "Model created: {$path}\n");

        return 0;
    }
}
