<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

/**
 * Value object handed back from DriverInterface::pop().
 *
 * Holds enough state to run, release, delete, or fail the job. The driver
 * decides what `id` represents (a database row id, an in-memory token, etc).
 */
final class ReservedJob
{
    public function __construct(
        public readonly Job $job,
        public readonly string $queue,
        public readonly int $attempts,
        public readonly array $payload,
        public readonly mixed $id = null,
    ) {}

    public function tries(): ?int
    {
        $tries = $this->payload['tries'] ?? null;

        return is_int($tries) && $tries > 0 ? $tries : null;
    }

    public function backoff(): ?int
    {
        $backoff = $this->payload['backoff'] ?? null;

        return is_int($backoff) && $backoff >= 0 ? $backoff : null;
    }
}
