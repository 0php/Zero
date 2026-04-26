<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

/**
 * Contract for a queueable job.
 *
 * Implementations live in app/jobs/. The constructor receives the args passed
 * to dispatch(); handle() is invoked by the worker. failed() is optional and
 * runs once after the final retry exhausts.
 */
interface Job
{
    public function handle(): void;
}
