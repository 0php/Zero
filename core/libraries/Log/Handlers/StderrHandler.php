<?php

declare(strict_types=1);

namespace Zero\Lib\Log\Handlers;

use DateTimeImmutable;
use Zero\Lib\Log\LogHandlerInterface;

final class StderrHandler implements LogHandlerInterface
{
    public function handle(
        DateTimeImmutable $timestamp,
        string $level,
        mixed $message,
        array $context,
        array $record,
        array $channel
    ): void {
        $output = $record['cli'] ?? $record['plain'] ?? '';

        if (defined('STDERR')) {
            @fwrite(STDERR, $output);
            return;
        }

        error_log($record['plain'] ?? '', 3, $channel['stream'] ?? 'php://stderr');
    }
}
