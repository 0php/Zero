<?php

declare(strict_types=1);

namespace Zero\Lib\Log;

use DateTimeImmutable;

interface LogHandlerInterface
{
    /**
     * Handle a log record.
     *
     * @param array<string, mixed> $channel Configuration for the current channel
     */
    public function handle(
        DateTimeImmutable $timestamp,
        string $level,
        mixed $message,
        array $context,
        array $record,
        array $channel
    ): void;
}
