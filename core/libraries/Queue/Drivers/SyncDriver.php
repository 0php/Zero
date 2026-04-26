<?php

declare(strict_types=1);

namespace Zero\Lib\Queue\Drivers;

use Throwable;
use Zero\Lib\Queue\Job;
use Zero\Lib\Queue\ReservedJob;

/**
 * Synchronous "queue" driver that runs the job inline on push().
 *
 * Useful as the default in tests and dev. Does not persist anything.
 * pop() always returns null — there is nothing to pop after push() has run.
 */
final class SyncDriver implements DriverInterface
{
    public function push(Job $job, ?string $queue = null): void
    {
        try {
            $job->handle();
        } catch (Throwable $exception) {
            if (method_exists($job, 'failed')) {
                try {
                    $job->failed($exception);
                } catch (Throwable) {
                    // swallow — primary exception below is what matters
                }
            }

            throw $exception;
        }
    }

    public function later(int $delaySeconds, Job $job, ?string $queue = null): void
    {
        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }

        $this->push($job, $queue);
    }

    public function pop(array $queues): ?ReservedJob
    {
        return null;
    }

    public function release(ReservedJob $job, int $delaySeconds): void
    {
        // no-op
    }

    public function delete(ReservedJob $job): void
    {
        // no-op
    }

    public function fail(ReservedJob $job, Throwable $exception): void
    {
        // no-op
    }

    public function size(?string $queue = null): int
    {
        return 0;
    }
}
