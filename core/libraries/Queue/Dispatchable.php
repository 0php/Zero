<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

/**
 * Mixin for Job classes. Adds static dispatch helpers so callers can write
 *
 *     SendOrderReceipt::dispatch($order->id);
 *     SendOrderReceipt::dispatchSync($order->id);
 *
 * instead of constructing the job manually.
 */
trait Dispatchable
{
    /**
     * Dispatch the job onto the default connection. Returns a fluent
     * PendingDispatch so callers can chain ->onQueue(), ->delay(), etc.
     */
    public static function dispatch(mixed ...$arguments): PendingDispatch
    {
        return new PendingDispatch(new static(...$arguments));
    }

    /**
     * Run the job immediately on the sync driver, regardless of the default
     * connection. Useful in tests or when the work must complete inline.
     */
    public static function dispatchSync(mixed ...$arguments): void
    {
        (new Drivers\SyncDriver())->push(new static(...$arguments));
    }

    /**
     * Dispatch the job after the HTTP response has been flushed. Returns
     * the same fluent PendingDispatch so callers can still chain
     * ->onQueue() / ->onConnection() / ->delay().
     */
    public static function dispatchAfterResponse(mixed ...$arguments): PendingDispatch
    {
        return (new PendingDispatch(new static(...$arguments)))->afterResponse();
    }
}
