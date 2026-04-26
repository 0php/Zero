<?php

declare(strict_types=1);

namespace Zero\Lib\Queue\Drivers;

use Throwable;
use Zero\Lib\Queue\Job;
use Zero\Lib\Queue\ReservedJob;

/**
 * Contract every queue driver implements.
 *
 * Drivers are responsible for persistence and worker-safe pop semantics.
 * Job execution itself lives in Worker; drivers must not invoke handle()
 * (the SyncDriver is the documented exception).
 */
interface DriverInterface
{
    /**
     * Enqueue a job for immediate processing.
     */
    public function push(Job $job, ?string $queue = null): void;

    /**
     * Enqueue a job for processing after a delay.
     */
    public function later(int $delaySeconds, Job $job, ?string $queue = null): void;

    /**
     * Atomically reserve the next available job from one of the given queues.
     *
     * Returns null when nothing is due. Drivers that cannot pop (sync) return null.
     *
     * @param array<int, string> $queues
     */
    public function pop(array $queues): ?ReservedJob;

    /**
     * Release a reserved job back to the queue with a delay.
     */
    public function release(ReservedJob $job, int $delaySeconds): void;

    /**
     * Permanently delete a reserved job (success path).
     */
    public function delete(ReservedJob $job): void;

    /**
     * Move a reserved job to the failed-jobs store.
     */
    public function fail(ReservedJob $job, Throwable $exception): void;

    /**
     * Approximate count of jobs waiting on a queue.
     */
    public function size(?string $queue = null): int;
}
