<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

use Throwable;
use Zero\Lib\Log;
use Zero\Lib\Queue\Drivers\DriverInterface;

/**
 * The worker loop. Holds no per-process state of its own — it asks the
 * driver for the next job, runs it, and updates the driver based on the
 * outcome. Used by QueueWorkCommand and any other long-running entry point.
 */
final class Worker
{
    private bool $shouldQuit = false;

    public function __construct(private DriverInterface $driver) {}

    public function run(WorkerOptions $options): int
    {
        $this->registerSignalHandlers();

        do {
            if ($this->shouldQuit) {
                break;
            }

            $reserved = $this->driver->pop($options->queues);

            if ($reserved === null) {
                if ($options->once) {
                    return 0;
                }

                $this->dispatchPendingSignals();
                if ($this->shouldQuit) {
                    break;
                }

                sleep(max(1, $options->sleep));
                continue;
            }

            $this->process($reserved, $options);

            if ($options->once) {
                return 0;
            }

            $this->dispatchPendingSignals();
        } while (! $this->shouldQuit);

        return 0;
    }

    private function process(ReservedJob $reserved, WorkerOptions $options): void
    {
        $jobClass = $reserved->job::class;
        $started = microtime(true);

        Log::channel('internal')->info('Processing queued job.', [
            'job' => $jobClass,
            'queue' => $reserved->queue,
            'attempt' => $reserved->attempts,
        ]);

        try {
            $reserved->job->handle();

            $elapsedMs = (int) ((microtime(true) - $started) * 1000);
            $this->driver->delete($reserved);

            $this->writeStatus(sprintf('Processed: %s (%dms)', $jobClass, $elapsedMs));

            Log::channel('internal')->info('Queued job completed.', [
                'job' => $jobClass,
                'queue' => $reserved->queue,
                'duration_ms' => $elapsedMs,
            ]);
        } catch (Throwable $exception) {
            $maxTries = max(1, $reserved->tries() ?? $options->tries);
            $jobBackoff = $reserved->backoff() ?? $options->backoff;

            Log::channel('internal')->error('Queued job threw.', [
                'job' => $jobClass,
                'queue' => $reserved->queue,
                'attempt' => $reserved->attempts,
                'tries' => $maxTries,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            if ($reserved->attempts >= $maxTries) {
                $this->driver->fail($reserved, $exception);
                $this->fireFailedHook($reserved, $exception);
                $this->writeStatus(sprintf('Failed: %s — %s', $jobClass, $exception->getMessage()));

                return;
            }

            $this->driver->release($reserved, max(0, $jobBackoff));
            $this->writeStatus(sprintf('Retrying: %s (attempt %d/%d in %ds)', $jobClass, $reserved->attempts, $maxTries, $jobBackoff));
        }
    }

    private function fireFailedHook(ReservedJob $reserved, Throwable $exception): void
    {
        if (! method_exists($reserved->job, 'failed')) {
            return;
        }

        try {
            $reserved->job->failed($exception);
        } catch (Throwable $hookFailure) {
            Log::channel('internal')->warning('Job failed() hook threw.', [
                'job' => $reserved->job::class,
                'exception' => $hookFailure::class,
                'message' => $hookFailure->getMessage(),
            ]);
        }
    }

    private function registerSignalHandlers(): void
    {
        if (! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(false);

        foreach ([SIGTERM, SIGINT, SIGQUIT] as $signal) {
            pcntl_signal($signal, function () {
                $this->shouldQuit = true;
            });
        }
    }

    private function dispatchPendingSignals(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }

    private function writeStatus(string $message): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
        fwrite(STDOUT, $line);
    }
}
