<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

/**
 * Fluent builder returned by dispatch(). Auto-flushes on destruction so
 *
 *     dispatch(new MyJob($id));
 *
 * works with no terminator. Chaining ->onQueue/->delay/->onConnection
 * configures the push before the destructor runs.
 */
final class PendingDispatch
{
    private ?string $queue = null;
    private ?string $connection = null;
    private int $delay = 0;
    private bool $afterResponse = false;
    private bool $dispatched = false;

    public function __construct(private Job $job) {}

    public function onQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function onConnection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function delay(int $seconds): self
    {
        $this->delay = max(0, $seconds);

        return $this;
    }

    /**
     * Defer the actual push until after the HTTP response has been flushed
     * to the client. Combine with `onConnection('sync')` for inline work that
     * shouldn't slow the response.
     */
    public function afterResponse(): self
    {
        $this->afterResponse = true;

        return $this;
    }

    /**
     * Flush the dispatch immediately. Idempotent.
     */
    public function dispatch(): void
    {
        if ($this->dispatched) {
            return;
        }

        $this->dispatched = true;

        if ($this->afterResponse) {
            $job = $this->job;
            $queue = $this->queue;
            $connection = $this->connection;
            $delay = $this->delay;

            AfterResponse::defer(static function () use ($job, $queue, $connection, $delay): void {
                if ($delay > 0) {
                    Queue::later($delay, $job, $queue, $connection);

                    return;
                }

                Queue::push($job, $queue, $connection);
            });

            return;
        }

        if ($this->delay > 0) {
            Queue::later($this->delay, $this->job, $this->queue, $this->connection);

            return;
        }

        Queue::push($this->job, $this->queue, $this->connection);
    }

    public function __destruct()
    {
        $this->dispatch();
    }
}
