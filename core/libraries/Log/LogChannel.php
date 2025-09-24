<?php

declare(strict_types=1);

namespace Zero\Lib\Log;

use Zero\Lib\Log as BaseLog;

final class LogChannel
{
    /** @var string */
    private string $name;

    public function __construct(string $name)
    {
        $this->name = trim($name);
    }

    public function emergency(mixed $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(mixed $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(mixed $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(mixed $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(mixed $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(mixed $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(mixed $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(mixed $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, mixed $message, array $context = []): void
    {
        BaseLog::writeToChannel($this->name !== '' ? $this->name : null, $level, $message, $context);
    }
}
