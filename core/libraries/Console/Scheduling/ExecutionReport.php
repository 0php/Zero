<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Scheduling;

final class ExecutionReport
{
    private int $succeeded = 0;

    private int $failed = 0;

    private int $skipped = 0;

    /**
     * @var array<int, array{task:string, exception:?\Throwable}>
     */
    private array $failures = [];

    public function recordSuccess(): void
    {
        $this->succeeded++;
    }

    public function recordFailure(string $task, ?\Throwable $exception): void
    {
        $this->failed++;
        $this->failures[] = [
            'task' => $task,
            'exception' => $exception,
        ];
    }

    public function recordSkip(): void
    {
        $this->skipped++;
    }

    public function total(): int
    {
        return $this->succeeded + $this->failed + $this->skipped;
    }

    public function succeeded(): int
    {
        return $this->succeeded;
    }

    public function failed(): int
    {
        return $this->failed;
    }

    public function skipped(): int
    {
        return $this->skipped;
    }

    /**
     * @return array<int, array{task:string, exception:?\Throwable}>
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
