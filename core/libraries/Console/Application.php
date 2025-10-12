<?php

declare(strict_types=1);

namespace Zero\Lib\Console;

use ReflectionMethod;
use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Input;
use Zero\Lib\Console\Commands\KeyGenerateCommand;
use Zero\Lib\Console\Commands\MakeCommandCommand;
use Zero\Lib\Console\Commands\MakeControllerCommand;
use Zero\Lib\Console\Commands\MakeHelperCommand;
use Zero\Lib\Console\Commands\MakeLoggerCommand;
use Zero\Lib\Console\Commands\MakeMigrationCommand;
use Zero\Lib\Console\Commands\MakeMiddlewareCommand;
use Zero\Lib\Console\Commands\MakeModelCommand;
use Zero\Lib\Console\Commands\MakeSeederCommand;
use Zero\Lib\Console\Commands\MakeServiceCommand;
use Zero\Lib\Console\Commands\MigrateCommand;
use Zero\Lib\Console\Commands\MigrateFreshCommand;
use Zero\Lib\Console\Commands\MigrateRefreshCommand;
use Zero\Lib\Console\Commands\RollbackCommand;
use Zero\Lib\Console\Commands\SeedCommand;
use Zero\Lib\Console\Commands\RouteListCommand;
use Zero\Lib\Console\Commands\ServeCommand;
use Zero\Lib\Console\Commands\StorageLinkCommand;
use Zero\Lib\Console\Commands\ScheduleRunCommand;
use Zero\Lib\Console\Commands\LogClearCommand;
use Zero\Lib\Console\Commands\DatabaseDumpCommand;
use Zero\Lib\Console\Commands\DatabaseRestoreCommand;

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
        $this->addCommand(new ServeCommand());
        $this->addCommand(new MakeControllerCommand());
        $this->addCommand(new MakeHelperCommand());
        $this->addCommand(new MakeLoggerCommand());
        $this->addCommand(new MakeModelCommand());
        $this->addCommand(new MakeMigrationCommand());
        $this->addCommand(new MigrateCommand());
        $this->addCommand(new MigrateFreshCommand());
        $this->addCommand(new MigrateRefreshCommand());
        $this->addCommand(new RollbackCommand());
        $this->addCommand(new MakeSeederCommand());
        $this->addCommand(new MakeMiddlewareCommand());
        $this->addCommand(new MakeServiceCommand());
        $this->addCommand(new MakeCommandCommand());
        $this->addCommand(new SeedCommand());
        $this->addCommand(new RouteListCommand());
        $this->addCommand(new KeyGenerateCommand());
        $this->addCommand(new StorageLinkCommand());
        $this->addCommand(new ScheduleRunCommand());
        $this->addCommand(new LogClearCommand());
        $this->addCommand(new DatabaseDumpCommand());
        $this->addCommand(new DatabaseRestoreCommand());

        $this->loadApplicationCommands();
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

        $handler = $this->commands[$command];
        $input = $this->buildInput($argv);

        $method = new ReflectionMethod($handler, 'execute');
        $parameterCount = $method->getNumberOfParameters();

        if ($parameterCount >= 2) {
            return $handler->execute($argv, $input);
        }

        return $handler->execute($argv);
    }

    public function addCommand(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    private function loadApplicationCommands(): void
    {
        if (class_exists('App\\Console\\Commands\\Command')) {
            $this->bootCommandRegistrar('App\\Console\\Commands\\Command');

            return;
        }

        if (class_exists('App\\Console\\Command')) {
            $this->bootCommandRegistrar('App\\Console\\Command');

            return;
        }

        if (class_exists('App\\Console\\Kernel')) {
            $this->bootLegacyKernel('App\\Console\\Kernel');
        }
    }

    private function bootCommandRegistrar(string $registrarClass): void
    {
        try {
            $registrar = new $registrarClass();
        } catch (\Throwable) {
            return;
        }

        if (method_exists($registrar, 'boot')) {
            try {
                $registrar->boot($this);
            } catch (\Throwable) {
                // swallow userland exceptions during registration
            }

            return;
        }

        if (method_exists($registrar, 'register')) {
            try {
                $registrar->register($this);
            } catch (\Throwable) {
                // ignore userland errors
            }
        }
    }

    private function bootLegacyKernel(string $kernelClass): void
    {
        try {
            $kernel = new $kernelClass();
        } catch (\Throwable) {
            return;
        }

        if (method_exists($kernel, 'register')) {
            try {
                $kernel->register($this);
            } catch (\Throwable) {
                return;
            }

            return;
        }

        if (! method_exists($kernel, 'commands')) {
            return;
        }

        try {
            $commands = $kernel->commands();
        } catch (\Throwable) {
            return;
        }

        foreach ((array) $commands as $command) {
            $resolved = $this->resolveCommand($command);

            if ($resolved !== null) {
                $this->addCommand($resolved);
            }
        }
    }

    private function buildInput(array $argv): Input
    {
        $arguments = [];
        $options = [];
        $count = count($argv);

        for ($i = 2; $i < $count; $i++) {
            $token = $argv[$i];

            if ($token === '--') {
                $arguments = array_merge($arguments, array_slice($argv, $i + 1));
                break;
            }

            if (str_starts_with($token, '--')) {
                $segment = substr($token, 2);
                if ($segment === '') {
                    continue;
                }

                if (str_contains($segment, '=')) {
                    [$key, $value] = explode('=', $segment, 2);
                } else {
                    $key = $segment;
                    if ($i + 1 < $count && !str_starts_with((string) $argv[$i + 1], '-')) {
                        $value = $argv[++$i];
                    } else {
                        $value = true;
                    }
                }

                $options[$key] = $value;
                continue;
            }

            if (str_starts_with($token, '-')) {
                $flags = substr($token, 1);
                if ($flags === '') {
                    continue;
                }

                foreach (str_split($flags) as $flag) {
                    $options[$flag] = true;
                }

                continue;
            }

            $arguments[] = $token;
        }

        return new Input($arguments, $options);
    }

    /**
     * @param class-string<CommandInterface>|CommandInterface $command
     */
    private function resolveCommand(string|CommandInterface $command): ?CommandInterface
    {
        if ($command instanceof CommandInterface) {
            return $command;
        }

        if (! class_exists($command)) {
            return null;
        }

        try {
            $instance = new $command();
        } catch (\Throwable) {
            return null;
        }

        return $instance instanceof CommandInterface ? $instance : null;
    }

    private function displayHelp(?string $topic = null): void
    {
        if ($topic !== null && isset($this->commands[$topic])) {
            $command = $this->commands[$topic];
            $this->writeInternal('Zero Framework CLI');
            $this->writeInternal('');
            $this->writeInternal('Usage:');
            $this->writeInternal('  ' . $command->getUsage());
            $this->writeInternal('');
            $this->writeInternal('Description:');
            $this->writeInternal('  ' . $command->getDescription());

            if ($topic === 'serve') {
                $this->describeServeOptions();
            }

            return;
        }

        if ($topic !== null && $topic !== 'help') {
            $this->writeInternal("Unknown command \"{$topic}\".", 'error');
            $this->writeInternal('');
        }

        $this->writeInternal('Zero Framework CLI');
        $this->writeInternal('');
        $this->writeInternal('Available commands:');

        foreach ($this->sortedCommands() as $command) {
            $this->writeInternal(sprintf("  %-17s %s", $command->getName(), $command->getDescription()));
        }

        $this->writeInternal('  help              Display this information');
        $this->writeInternal('');
        $this->writeInternal('Run "php zero help <command>" for details on a specific command.');
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
        $this->writeInternal('');
        $this->writeInternal('Options:');
        $this->writeInternal(sprintf("  --host            Specify the host (default: %s)", self::DEFAULT_HOST));
        $this->writeInternal(sprintf("  --port            Specify the port (default: %s)", self::DEFAULT_PORT));
        $this->writeInternal(sprintf("  --root            Document root (default: %s)", self::DEFAULT_DOCROOT));
        $this->writeInternal('  --franken         Use the Franken server backend');
        $this->writeInternal('  --swolee          Use the Swoole server backend');
        $this->writeInternal('  --watch           Enable file watching (experimental)');
    }

    private function writeInternal(string $message, string $level = 'info'): void
    {
        $channel = \Zero\Lib\Log::channel('internal');

        if (! method_exists($channel, $level)) {
            $level = 'info';
        }

        $channel->{$level}($message);
    }
}
