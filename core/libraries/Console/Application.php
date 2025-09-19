<?php

declare(strict_types=1);

namespace Zero\Lib\Console;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Commands\MakeControllerCommand;
use Zero\Lib\Console\Commands\MakeMigrationCommand;
use Zero\Lib\Console\Commands\MakeModelCommand;
use Zero\Lib\Console\Commands\MakeSeederCommand;
use Zero\Lib\Console\Commands\MigrateCommand;
use Zero\Lib\Console\Commands\RollbackCommand;
use Zero\Lib\Console\Commands\SeedCommand;
use Zero\Lib\Console\Commands\ServeCommand;

final class Application
{
    public const DEFAULT_HOST = '127.0.0.1';
    public const DEFAULT_PORT = '8000';
    public const DEFAULT_DOCROOT = 'public';

    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    public function __construct()
    {
        $this->register(new ServeCommand());
        $this->register(new MakeControllerCommand());
        $this->register(new MakeModelCommand());
        $this->register(new MakeMigrationCommand());
        $this->register(new MigrateCommand());
        $this->register(new RollbackCommand());
        $this->register(new MakeSeederCommand());
        $this->register(new SeedCommand());
    }

    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        if ($command === 'help' || $command === '--help' || $command === '-h') {
            $topic = $argv[2] ?? null;
            $this->displayHelp($topic);

            return 0;
        }

        if (! isset($this->commands[$command])) {
            $this->displayHelp($command);

            return 1;
        }

        return $this->commands[$command]->execute($argv);
    }

    private function register(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    private function displayHelp(?string $topic = null): void
    {
        if ($topic !== null && isset($this->commands[$topic])) {
            $command = $this->commands[$topic];
            fwrite(STDOUT, "Zero Framework CLI\n\n");
            fwrite(STDOUT, "Usage:\n  " . $command->getUsage() . "\n\n");
            fwrite(STDOUT, "Description:\n  " . $command->getDescription() . "\n");

            if ($topic === 'serve') {
                $this->describeServeOptions();
            }

            return;
        }

        if ($topic !== null && $topic !== 'help') {
            fwrite(STDERR, "Unknown command \"{$topic}\".\n\n");
        }

        fwrite(STDOUT, "Zero Framework CLI\n\n");
        fwrite(STDOUT, "Available commands:\n");

        foreach ($this->sortedCommands() as $command) {
            fwrite(
                STDOUT,
                sprintf("  %-17s %s\n", $command->getName(), $command->getDescription())
            );
        }

        fwrite(STDOUT, "  help              Display this information\n\n");
        fwrite(STDOUT, "Run \"php zero help <command>\" for details on a specific command.\n");
    }

    /**
     * @return array<int, CommandInterface>
     */
    private function sortedCommands(): array
    {
        $commands = array_values($this->commands);
        usort($commands, static fn (CommandInterface $a, CommandInterface $b): int => $a->getName() <=> $b->getName());

        return $commands;
    }

    private function describeServeOptions(): void
    {
        fwrite(STDOUT, "\nOptions:\n");
        fwrite(STDOUT, sprintf("  --host            Specify the host (default: %s)\n", self::DEFAULT_HOST));
        fwrite(STDOUT, sprintf("  --port            Specify the port (default: %s)\n", self::DEFAULT_PORT));
        fwrite(STDOUT, sprintf("  --root            Document root (default: %s)\n", self::DEFAULT_DOCROOT));
        fwrite(STDOUT, "  --franken         Use the Franken server backend\n");
        fwrite(STDOUT, "  --swolee          Use the Swoole server backend\n");
        fwrite(STDOUT, "  --watch           Enable file watching (experimental)\n");
    }
}
