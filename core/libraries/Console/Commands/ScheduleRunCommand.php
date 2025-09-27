<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Throwable;
use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Input;
use Zero\Lib\Console\Scheduling\ExecutionReport;
use Zero\Lib\Console\Scheduling\Schedule;
use Zero\Lib\Console\Scheduling\Scheduler;
use Zero\Lib\Log;

final class ScheduleRunCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'schedule:run';
    }

    public function getDescription(): string
    {
        return 'Execute the scheduled tasks that are due.';
    }

    public function getUsage(): string
    {
        return 'php zero schedule:run [--path=routes/cron.php]';
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
            Log::channel('internal')->info('Scheduler definition not found; skipping run.', [
                'path' => $schedulePath,
            ]);

            return 0;
        }

        try {
            $schedule = $this->loadSchedule($schedulePath);
        } catch (Throwable $exception) {
            Log::channel('internal')->error('Failed to load the scheduler definition.', [
                'path' => $schedulePath,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return 1;
        }

        if ($schedule === null || $schedule->isEmpty()) {
            Log::channel('internal')->info('No scheduled tasks registered; nothing to run.', [
                'path' => $schedulePath,
            ]);

            return 0;
        }

        $scheduler = new Scheduler($schedule);
        $report = $scheduler->run();

        $this->logSummary($report);

        if ($report->failed() > 0) {
            foreach ($report->failures() as $failure) {
                $exception = $failure['exception'];

                Log::channel('internal')->error('Scheduled task failed during execution.', [
                    'task' => $failure['task'],
                    'exception' => $exception?->getMessage(),
                ]);
            }

            return 1;
        }

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

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':');
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

    private function logSummary(ExecutionReport $report): void
    {
        Log::channel('internal')->info('Scheduler run completed.', [
            'executed' => $report->succeeded(),
            'failed' => $report->failed(),
            'skipped' => $report->skipped(),
        ]);
    }
}
