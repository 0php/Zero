<?php

declare(strict_types=1);

namespace Zero\Lib;

use DateTimeImmutable;
use Zero\Lib\DB\LogRepository;

class Log
{
    protected static ?string $customPath = null;

    public static function setPath(?string $path): void
    {
        static::$customPath = $path;
    }

    public static function emergency(string $message, array $context = []): void
    {
        static::write('emergency', $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        static::write('alert', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        static::write('critical', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        static::write('error', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        static::write('warning', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        static::write('notice', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        static::write('info', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        static::write('debug', $message, $context);
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $timestamp = new DateTimeImmutable('now');
        $config = static::configuration();
        $driver = $config['default'] ?? 'file';
        $channels = $config['channels'] ?? [];
        $channelConfig = $channels[$driver] ?? [];

        if ($driver === 'database') {
            $written = static::writeDatabase($timestamp, $level, $message, $context, $channelConfig);

            if ($written) {
                return;
            }

            $driver = 'file';
            $channelConfig = $channels['file'] ?? [];
        }

        $formatted = static::formatMessage($timestamp, $level, $message, $context);
        static::writeFile($timestamp, $formatted, $channelConfig);
    }

    protected static function resolveDirectory(?string $configuredPath = null): string
    {
        if (static::$customPath) {
            return rtrim(static::$customPath, '/');
        }

        if ($configuredPath) {
            return rtrim($configuredPath, '/');
        }

        if (function_exists('storage_path')) {
            return rtrim(storage_path('framework/logs'), '/');
        }

        return dirname(__DIR__, 2) . '/storage/framework/logs';
    }

    protected static function formatMessage(DateTimeImmutable $timestamp, string $level, string $message, array $context): string
    {
        $prefix = '[' . $timestamp->format('Y-m-d H:i:s') . '] ' . strtoupper($level);

        if (!empty($context)) {
            $message .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $prefix . ': ' . $message . PHP_EOL;
    }

    protected static function writeFile(DateTimeImmutable $timestamp, string $formatted, array $channel): void
    {
        $directory = static::resolveDirectory($channel['path'] ?? null);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            return;
        }

        $logFile = $directory . '/' . $timestamp->format('Y-m-d') . '.log';
        file_put_contents($logFile, $formatted, FILE_APPEND);
    }

    protected static function writeDatabase(DateTimeImmutable $timestamp, string $level, string $message, array $context, array $channel): bool
    {
        if (!class_exists(LogRepository::class)) {
            return false;
        }

        try {
            $repository = new LogRepository($channel['table'] ?? 'logs');

            return $repository->store(
                $level,
                $message,
                $context,
                $timestamp->format('Y-m-d H:i:s')
            );
        } catch (\Throwable $e) {
            error_log('Log database write failed: ' . $e->getMessage());

            return false;
        }
    }

    protected static function configuration(): array
    {
        static $config;

        if ($config !== null) {
            return $config;
        }

        if (function_exists('config')) {
            try {
                $loaded = config('logging');
                if (is_array($loaded)) {
                    $config = $loaded;
                    return $config;
                }
            } catch (\Throwable) {
                // ignore and fall back to defaults
            }
        }

        $config = [
            'default' => 'file',
            'channels' => [
                'file' => [
                    'path' => null,
                ],
            ],
        ];

        return $config;
    }
}
