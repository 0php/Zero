<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Console\Support\Str;
use Zero\Lib\Template;

final class MakeMigrationCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:migration';
    }

    public function getDescription(): string
    {
        return 'Generate a database migration stub';
    }

    public function getUsage(): string
    {
        return 'php zero make:migration Name [--force]';
    }

    public function execute(array $argv): int
    {
        $name = $argv[2] ?? null;
        $force = in_array('--force', $argv, true);

        if ($name === null) {
            fwrite(STDERR, "Usage: {$this->getUsage()}\n");

            return 1;
        }

        $timestamp = date('Y_m_d_His');
        $fileName = $timestamp . '_' . Str::snake($name) . '.php';
        $path = base('database/migrations/' . $fileName);

        if (file_exists($path) && ! $force) {
            fwrite(STDERR, "Migration {$fileName} already exists. Use --force to overwrite.\n");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $contents = Template::render('migration.tmpl', [
            'table' => $this->guessMigrationTable($name),
        ]);

        file_put_contents($path, $contents);
        fwrite(STDOUT, "Migration created: {$path}\n");

        return 0;
    }

    private function guessMigrationTable(string $name): string
    {
        $snake = Str::snake($name);

        if (preg_match('/create_(.+)_table/', $snake, $matches)) {
            return $matches[1];
        }

        if (preg_match('/add_.*_to_(.+)_table/', $snake, $matches)) {
            return $matches[1];
        }

        if (preg_match('/_table$/', $snake)) {
            return preg_replace('/_table$/', '', $snake);
        }

        return 'table_name';
    }
}
