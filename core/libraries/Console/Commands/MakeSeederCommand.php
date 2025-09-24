<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Support\Str;
use Zero\Lib\Template;

final class MakeSeederCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:seeder';
    }

    public function getDescription(): string
    {
        return 'Generate a database seeder class';
    }

    public function getUsage(): string
    {
        return 'php zero make:seeder Name [--force]';
    }

    public function execute(array $argv): int
    {
        $name = $argv[2] ?? null;
        $force = in_array('--force', $argv, true);

        if ($name === null) {
            \Zero\Lib\Log::channel('internal')->error("Usage: {$this->getUsage()}");

            return 1;
        }

        $className = Str::studly($name);
        $path = base('database/seeders/' . $className . '.php');

        if (file_exists($path) && ! $force) {
            \Zero\Lib\Log::channel('internal')->error("Seeder {$className} already exists. Use --force to overwrite.");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $contents = Template::render('seeder.tmpl', [
            'class' => $className,
        ]);

        file_put_contents($path, $contents);
        \Zero\Lib\Log::channel('internal')->info("Seeder created: {$path}");

        return 0;
    }
}
