<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

final class WorkerOptions
{
    /**
     * @param array<int, string> $queues
     */
    public function __construct(
        public readonly string $connection = 'sync',
        public readonly array $queues = ['default'],
        public readonly int $tries = 1,
        public readonly int $backoff = 0,
        public readonly int $sleep = 3,
        public readonly bool $once = false,
    ) {}
}
