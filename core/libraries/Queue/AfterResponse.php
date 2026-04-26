<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

use Throwable;
use Zero\Lib\Log;

/**
 * Runs queued callbacks after the HTTP response has been flushed to the
 * client. Uses fastcgi_finish_request() when available (PHP-FPM); falls
 * back to register_shutdown_function() otherwise.
 *
 * Each queued callback is independent — an exception in one does not
 * prevent the others from running. All exceptions are logged on the
 * `internal` channel.
 */
final class AfterResponse
{
    /** @var array<int, callable> */
    private static array $callbacks = [];

    private static bool $registered = false;

    public static function defer(callable $callback): void
    {
        self::$callbacks[] = $callback;

        self::registerShutdown();
    }

    /**
     * Run every queued callback. Idempotent: callbacks are cleared on flush.
     */
    public static function flush(): void
    {
        if (self::$callbacks === []) {
            return;
        }

        $callbacks = self::$callbacks;
        self::$callbacks = [];

        // Send any buffered output, then finish the FastCGI request so the
        // client gets the response immediately and we can keep running.
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (Throwable $exception) {
                Log::channel('internal')->error('after-response callback threw.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * Forget every pending callback without invoking them. Useful in tests.
     */
    public static function reset(): void
    {
        self::$callbacks = [];
    }

    public static function pending(): int
    {
        return count(self::$callbacks);
    }

    private static function registerShutdown(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        register_shutdown_function(static function (): void {
            self::flush();
        });
    }
}
