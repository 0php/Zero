<?php

declare(strict_types=1);

namespace Zero\Lib\Support;

use Zero\Lib\Console\Application;
use Zero\Lib\Console\Command\CommandInterface;

trait RegistersConsoleCommands
{
    /**
     * @param array<int, class-string<CommandInterface>|CommandInterface>|class-string<CommandInterface>|CommandInterface $commands
     */
    protected function register(Application $application, array|string|CommandInterface $commands): void
    {
        $list = is_array($commands) ? $commands : [$commands];

        foreach ($list as $command) {
            $resolved = $command;

            if (is_string($command)) {
                if (! class_exists($command)) {
                    continue;
                }

                $candidate = new $command();
                if (! $candidate instanceof CommandInterface) {
                    continue;
                }

                $resolved = $candidate;
            }

            if (! $resolved instanceof CommandInterface) {
                continue;
            }

            $application->addCommand($resolved);
        }
    }

    /**
     * @deprecated Use register() instead. Will be removed in a future release.
     */
    protected function registerCommands(Application $application, array|string|CommandInterface $commands): void
    {
        $this->register($application, $commands);
    }
}
