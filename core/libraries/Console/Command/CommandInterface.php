<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Command;

interface CommandInterface
{
    /**
     * Command identifier used on the CLI (e.g. make:model).
     */
    public function getName(): string;

    /**
     * Short description displayed in the help listing.
     */
    public function getDescription(): string;

    /**
     * Usage line (e.g. "php zero make:model Name [--force]").
     */
    public function getUsage(): string;

    /**
     * Execute the command.
     *
     * @param array<int, string> $argv Raw CLI arguments.
     * @return int Exit status code (0 for success).
     */
    public function execute(array $argv): int;
}
