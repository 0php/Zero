<?php

declare(strict_types=1);

namespace Zero\Lib\Log\Handlers;

use DateTimeImmutable;
use Zero\Lib\Log\LogHandlerInterface;

final class InternalHandler implements LogHandlerInterface
{
    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $record Full log record (plain/cli representations, etc)
     * @param array<string,mixed> $channel Channel configuration
     */
    public function handle(
        DateTimeImmutable $timestamp,
        string $level,
        mixed $message,
        array $context,
        array $record,
        array $channel
    ): void {
        $payload = $this->stringify($message);
        if (!str_ends_with($payload, PHP_EOL)) {
            $payload .= PHP_EOL;
        }

        $stream = $channel['stream'] ?? 'php://stdout';

        if (defined('STDOUT') && is_resource(STDOUT)) {
            @fwrite(STDOUT, $payload);
            return;
        }

        $handle = @fopen($stream, 'ab');

        if (is_resource($handle)) {
            @fwrite($handle, $payload);
            fclose($handle);
        }
    }

    private function stringify(mixed $message): string
    {
        if (is_string($message)) {
            return $message;
        }

        if (is_scalar($message) || $message === null) {
            return (string) $message;
        }

        return trim(print_r($message, true));
    }
}
