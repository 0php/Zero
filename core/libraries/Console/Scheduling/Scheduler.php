<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use Zero\Lib\Console\Application;
use Zero\Lib\Log;

final class Scheduler
{
    private Schedule $schedule;

    private DateTimeInterface $now;

    private ?Application $application = null;

    public function __construct(Schedule $schedule, ?DateTimeInterface $now = null)
    {
        $this->schedule = $schedule;
        $this->now = $now ?? new DateTimeImmutable('now');
    }

    public function run(): ExecutionReport
    {
        $report = new ExecutionReport();

        foreach ($this->schedule->events() as $event) {
            $result = $event->run($this, $this->now);

            if ($result === Event::RESULT_SUCCESS) {
                $report->recordSuccess();
            } elseif ($result === Event::RESULT_FAILURE) {
                $report->recordFailure($event->getDescription(), $event->lastException());
            } else {
                $report->recordSkip();
            }
        }

        if ($report->total() === 0) {
            Log::channel('internal')->info('No scheduled tasks are defined.');
        }

        return $report;
    }

    public function runCommand(string $signature, array $arguments = []): int
    {
        if ($signature === 'schedule:run') {
            Log::channel('internal')->warning('Ignoring self-referential scheduled command "schedule:run".');

            return 0;
        }

        $argv = array_merge(['zero', $signature], $arguments);

        return $this->application()->run($argv);
    }

    private function application(): Application
    {
        if ($this->application !== null) {
            return $this->application;
        }

        $this->application = new Application();

        return $this->application;
    }
}
