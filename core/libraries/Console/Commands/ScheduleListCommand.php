<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use DateTimeImmutable;
use Throwable;
use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Input;
use Zero\Lib\Console\Scheduling\Schedule;

final class ScheduleListCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'schedule:list';
    }

    public function getDescription(): string
    {
        return 'List the registered scheduled tasks.';
    }

    public function getUsage(): string
    {
        return 'php zero schedule:list [--path=routes/cron.php]';
    }

    public function execute(array $argv, ?Input $input = null): int
    {
        $input ??= new Input([], []);

        $pathOption = $input->option('path');
        if ($pathOption === null) {
            $pathOption = $this->valueFromArgv($argv, '--path');
        }

        $schedulePath = $this->normalizePath($pathOption ?? 'routes/cron.php');

        if (! file_exists($schedulePath)) {
            fwrite(STDOUT, sprintf("Schedule file not found: %s\n", $schedulePath));

            return 0;
        }

        try {
            $schedule = $this->loadSchedule($schedulePath);
        } catch (Throwable $exception) {
            fwrite(STDERR, sprintf("Failed to load schedule: %s\n", $exception->getMessage()));

            return 1;
        }

        if ($schedule === null || $schedule->isEmpty()) {
            fwrite(STDOUT, "No scheduled tasks registered.\n");

            return 0;
        }

        $now = new DateTimeImmutable('now');
        $rows = [];
        foreach ($schedule->events() as $event) {
            $rows[] = [
                'task' => $event->getDescription(),
                'due_now' => $event->isDueAt($now) ? 'yes' : 'no',
            ];
        }

        $this->renderTable($rows);

        return 0;
    }

    private function loadSchedule(string $path): ?Schedule
    {
        Schedule::reset();

        $definition = require $path;

        if ($definition instanceof Schedule) {
            return $definition;
        }

        $schedule = Schedule::instance();

        if (is_callable($definition)) {
            $definition($schedule);

            return $schedule;
        }

        return $schedule->isEmpty() ? null : $schedule;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            $path = 'routes/cron.php';
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR)
            || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':')) {
            return $path;
        }

        return base($path);
    }

    private function valueFromArgv(array $argv, string $option): ?string
    {
        foreach ($argv as $argument) {
            if (! str_starts_with($argument, $option . '=')) {
                continue;
            }

            return substr($argument, strlen($option) + 1) ?: null;
        }

        return null;
    }

    /**
     * @param array<int, array{task:string, due_now:string}> $rows
     */
    private function renderTable(array $rows): void
    {
        $taskWidth = max(4, max(array_map(static fn ($r) => strlen($r['task']), $rows)));
        $dueWidth = 7;

        $line = '+' . str_repeat('-', $taskWidth + 2) . '+' . str_repeat('-', $dueWidth + 2) . "+\n";
        $header = sprintf("| %-{$taskWidth}s | %-{$dueWidth}s |\n", 'Task', 'Due now');

        fwrite(STDOUT, $line . $header . $line);
        foreach ($rows as $row) {
            fwrite(STDOUT, sprintf("| %-{$taskWidth}s | %-{$dueWidth}s |\n", $row['task'], $row['due_now']));
        }
        fwrite(STDOUT, $line);
    }
}
