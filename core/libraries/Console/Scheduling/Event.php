<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Scheduling;

use DateTimeInterface;
use Zero\Lib\Log;

abstract class Event
{
    public const RESULT_SKIPPED = 'skipped';
    public const RESULT_SUCCESS = 'success';
    public const RESULT_FAILURE = 'failure';

    /**
     * @var array<int, callable(DateTimeInterface): bool>
     */
    protected array $constraints = [];

    protected bool $preventOverlaps = false;

    protected int $overlapExpiresAfter = 3600;

    protected ?string $customMutexName = null;

    protected ?FileMutex $mutex = null;

    protected ?string $description = null;

    protected ?\Throwable $lastException = null;

    protected ?string $identifier = null;

    /**
     * @var array<string, string>
     */
    private static array $lastExecutionBuckets = [];

    public function __construct()
    {
        $this->everyMinute();
    }

    public function everyMinute(): static
    {
        return $this->everyMinutes(1);
    }

    public function everyTwoMinutes(): static
    {
        return $this->everyMinutes(2);
    }

    public function everyThreeMinutes(): static
    {
        return $this->everyMinutes(3);
    }

    public function everyFiveMinutes(): static
    {
        return $this->everyMinutes(5);
    }

    public function everyTenMinutes(): static
    {
        return $this->everyMinutes(10);
    }

    public function everyFifteenMinutes(): static
    {
        return $this->everyMinutes(15);
    }

    public function everyThirtyMinutes(): static
    {
        return $this->everyMinutes(30);
    }

    public function everyMinutes(int $interval): static
    {
        $interval = max(1, min(59, $interval));

        $this->setConstraint(static fn (DateTimeInterface $now): bool => ((int) $now->format('i')) % $interval === 0);

        return $this;
    }

    public function everyHours(int $interval, int $minute = 0): static
    {
        $interval = max(1, $interval);
        $minute = max(0, min(59, $minute));

        $this->setConstraint(static function (DateTimeInterface $now) use ($interval, $minute): bool {
            $hoursSinceEpoch = intdiv((int) $now->format('U'), 3600);

            return $hoursSinceEpoch % $interval === 0
                && (int) $now->format('i') === $minute;
        });

        return $this;
    }

    public function everySixHours(int $minute = 0): static
    {
        return $this->everyHours(6, $minute);
    }

    public function everyTwelveHours(int $minute = 0): static
    {
        return $this->everyHours(12, $minute);
    }

    public function everyThirtySixHours(int $minute = 0): static
    {
        return $this->everyHours(36, $minute);
    }

    public function hourly(): static
    {
        return $this->hourlyAt(0);
    }

    public function hourlyAt(int $minute): static
    {
        $minute = max(0, min(59, $minute));

        $this->setConstraint(static fn (DateTimeInterface $now): bool => (int) $now->format('i') === $minute);

        return $this;
    }

    public function daily(): static
    {
        return $this->dailyAt('00:00');
    }

    public function dailyAt(string $time): static
    {
        [$hour, $minute] = $this->parseHourMinute($time);

        $this->setConstraint(static fn (DateTimeInterface $now): bool =>
            (int) $now->format('H') === $hour && (int) $now->format('i') === $minute
        );

        return $this;
    }

    public function twiceDaily(int $firstHour = 1, int $secondHour = 13): static
    {
        $firstHour = max(0, min(23, $firstHour));
        $secondHour = max(0, min(23, $secondHour));

        $this->setConstraint(static fn (DateTimeInterface $now): bool =>
            (in_array((int) $now->format('H'), [$firstHour, $secondHour], true))
            && (int) $now->format('i') === 0
        );

        return $this;
    }

    public function weekdays(string $time = '00:00'): static
    {
        return $this
            ->dailyAt($time)
            ->addConstraint(static fn (DateTimeInterface $now): bool => (int) $now->format('N') <= 5);
    }

    public function weekends(string $time = '00:00'): static
    {
        return $this
            ->dailyAt($time)
            ->addConstraint(static fn (DateTimeInterface $now): bool => (int) $now->format('N') >= 6);
    }

    public function weekly(string $time = '00:00'): static
    {
        return $this->weeklyOn(1, $time);
    }

    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): static
    {
        $dayOfWeek = ($dayOfWeek % 7 + 7) % 7;

        return $this
            ->dailyAt($time)
            ->addConstraint(static fn (DateTimeInterface $now): bool => (int) $now->format('w') === $dayOfWeek);
    }

    public function daysOfWeek(int ...$days): static
    {
        if ($days === []) {
            return $this;
        }

        $normalized = [];
        foreach ($days as $day) {
            $normalized[] = ($day % 7 + 7) % 7;
        }

        $allowed = array_values(array_unique($normalized));

        return $this->addConstraint(static fn (DateTimeInterface $now): bool => in_array((int) $now->format('w'), $allowed, true));
    }

    public function sunday(): static
    {
        return $this->daysOfWeek(0);
    }

    public function monday(): static
    {
        return $this->daysOfWeek(1);
    }

    public function tuesday(): static
    {
        return $this->daysOfWeek(2);
    }

    public function wednesday(): static
    {
        return $this->daysOfWeek(3);
    }

    public function thursday(): static
    {
        return $this->daysOfWeek(4);
    }

    public function friday(): static
    {
        return $this->daysOfWeek(5);
    }

    public function saturday(): static
    {
        return $this->daysOfWeek(6);
    }

    public function monthly(int $dayOfMonth = 1, string $time = '00:00'): static
    {
        return $this->monthlyOn($dayOfMonth, $time);
    }

    public function monthlyOn(int $dayOfMonth, string $time = '00:00'): static
    {
        $dayOfMonth = max(1, min(31, $dayOfMonth));

        return $this
            ->dailyAt($time)
            ->addConstraint(static fn (DateTimeInterface $now): bool => (int) $now->format('j') === $dayOfMonth);
    }

    public function datesOfMonth(int ...$dates): static
    {
        if ($dates === []) {
            return $this;
        }

        $filtered = array_filter($dates, static fn (int $date): bool => $date >= 1 && $date <= 31);
        if ($filtered === []) {
            return $this;
        }

        $allowed = array_values(array_unique(array_map(static fn (int $date): int => $date, $filtered)));

        return $this->addConstraint(static fn (DateTimeInterface $now): bool => in_array((int) $now->format('j'), $allowed, true));
    }

    public function months(int ...$months): static
    {
        if ($months === []) {
            return $this;
        }

        $filtered = array_filter($months, static fn (int $month): bool => $month >= 1 && $month <= 12);
        if ($filtered === []) {
            return $this;
        }

        $allowed = array_values(array_unique(array_map(static fn (int $month): int => $month, $filtered)));

        return $this->addConstraint(static fn (DateTimeInterface $now): bool => in_array((int) $now->format('n'), $allowed, true));
    }

    public function hours(int ...$hours): static
    {
        if ($hours === []) {
            return $this;
        }

        $filtered = array_filter($hours, static fn (int $hour): bool => $hour >= 0 && $hour <= 23);
        if ($filtered === []) {
            return $this;
        }

        $allowed = array_values(array_unique(array_map(static fn (int $hour): int => $hour, $filtered)));

        return $this->addConstraint(static fn (DateTimeInterface $now): bool => in_array((int) $now->format('H'), $allowed, true));
    }

    public function minutes(int ...$minutes): static
    {
        if ($minutes === []) {
            return $this;
        }

        $filtered = array_filter($minutes, static fn (int $minute): bool => $minute >= 0 && $minute <= 59);
        if ($filtered === []) {
            return $this;
        }

        $allowed = array_values(array_unique(array_map(static fn (int $minute): int => $minute, $filtered)));

        return $this->addConstraint(static fn (DateTimeInterface $now): bool => in_array((int) $now->format('i'), $allowed, true));
    }

    public function quarterly(string $time = '00:00'): static
    {
        return $this
            ->monthlyOn(1, $time)
            ->addConstraint(static fn (DateTimeInterface $now): bool => in_array((int) $now->format('n'), [1, 4, 7, 10], true));
    }

    public function yearly(string $time = '00:00'): static
    {
        return $this
            ->monthlyOn(1, $time)
            ->addConstraint(static fn (DateTimeInterface $now): bool => (int) $now->format('n') === 1);
    }

    public function cron(string $expression): static
    {
        $expression = trim($expression);

        $this->setConstraint(static fn (DateTimeInterface $now) => CronExpression::isDue($expression, $now));

        return $this;
    }




    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function mutexName(string $name): static
    {
        $this->customMutexName = trim($name);
        $this->mutex = null;

        return $this;
    }

    public function withoutOverlapping(int $expiresAfterSeconds = 3600): static
    {
        $this->preventOverlaps = true;
        $this->overlapExpiresAfter = max(1, $expiresAfterSeconds);

        return $this;
    }

    public function run(Scheduler $scheduler, DateTimeInterface $now): string
    {
        $this->lastException = null;

        if (! $this->isDue($now)) {
            return self::RESULT_SKIPPED;
        }

        $fingerprint = $this->fingerprint();
        $bucket = $this->executionBucket($now);

        $lastBucket = self::$lastExecutionBuckets[$fingerprint] ?? $this->readLastExecutionBucket($fingerprint);
        if ($lastBucket === $bucket) {
            return self::RESULT_SKIPPED;
        }

        $description = $this->getDescription();
        $mutexAcquired = false;

        if ($this->preventOverlaps) {
            $mutex = $this->mutex();
            $mutexAcquired = $mutex->acquire($this->overlapExpiresAfter);

            if (! $mutexAcquired) {
                Log::channel('internal')->info('Skipping scheduled task because a previous run is still in progress.', [
                    'task' => $description,
                ]);

                return self::RESULT_SKIPPED;
            }
        }

        Log::channel('internal')->info('Running scheduled task.', [
            'task' => $description,
        ]);

        $executed = false;

        try {
            $executed = true;
            $this->execute($scheduler, $now);

            Log::channel('internal')->info('Completed scheduled task.', [
                'task' => $description,
            ]);

            return self::RESULT_SUCCESS;
        } catch (\Throwable $exception) {
            $this->lastException = $exception;

            Log::channel('internal')->error('Scheduled task failed.', [
                'task' => $description,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return self::RESULT_FAILURE;
        } finally {
            if ($executed) {
                self::$lastExecutionBuckets[$fingerprint] = $bucket;
                $this->storeExecutionBucket($fingerprint, $bucket);
            }

            if ($mutexAcquired) {
                $this->mutex()?->release();
            }
        }
    }

    public function lastException(): ?\Throwable
    {
        return $this->lastException;
    }

    public function getDescription(): string
    {
        return $this->description ?? $this->defaultDescription();
    }

    protected function isDue(DateTimeInterface $now): bool
    {
        foreach ($this->constraints as $constraint) {
            if (! $constraint($now)) {
                return false;
            }
        }

        return true;
    }

    abstract protected function execute(Scheduler $scheduler, DateTimeInterface $now): void;

    abstract protected function defaultDescription(): string;

    protected function fingerprint(): string
    {
        return static::class . '|' . ($this->identifier ?? $this->getDescription());
    }

    protected function executionBucket(DateTimeInterface $now): string
    {
        return $now->format('Y-m-d H:i');
    }

    private function readLastExecutionBucket(string $fingerprint): ?string
    {
        $path = $this->executionStatePath($fingerprint);

        if (! file_exists($path)) {
            return null;
        }

        $contents = trim((string) file_get_contents($path));

        return $contents !== '' ? $contents : null;
    }

    private function storeExecutionBucket(string $fingerprint, string $bucket): void
    {
        $directory = storage_path('framework/schedule');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $this->executionStatePath($fingerprint);
        file_put_contents($path, $bucket);
    }

    private function executionStatePath(string $fingerprint): string
    {
        return storage_path('framework/schedule/' . md5($fingerprint) . '.state');
    }

    protected function addConstraint(callable $constraint): static
    {
        $this->constraints[] = $constraint;

        return $this;
    }

    protected function setConstraint(callable $constraint): void
    {
        $this->constraints = [$constraint];
    }

    protected function mutex(): FileMutex
    {
        if ($this->mutex === null) {
            $this->mutex = new FileMutex($this->resolveMutexName());
        }

        return $this->mutex;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseHourMinute(string $time): array
    {
        $segments = explode(':', $time, 2);
        $hour = isset($segments[0]) ? (int) $segments[0] : 0;
        $minute = isset($segments[1]) ? (int) $segments[1] : 0;

        $hour = max(0, min(23, $hour));
        $minute = max(0, min(59, $minute));

        return [$hour, $minute];
    }

    private function resolveMutexName(): string
    {
        if ($this->customMutexName !== null && $this->customMutexName !== '') {
            return $this->customMutexName;
        }

        $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $this->getDescription()) ?? 'cron-event');
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            $normalized = 'cron-event';
        }

        return $normalized;
    }
}
