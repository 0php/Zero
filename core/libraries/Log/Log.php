<?php

declare(strict_types=1);

namespace Zero\Lib;

use DateTimeImmutable;
use Zero\Lib\DB\LogRepository;
use Zero\Lib\Log\Formatter;
use Zero\Lib\Log\LogChannel;
use Zero\Lib\Log\LogHandlerInterface;

class Log
{
    protected static ?string $customPath = null;

    public static function setPath(?string $path): void
    {
        static::$customPath = $path;
    }

    public static function channel(string $name): LogChannel
    {
        return new LogChannel($name);
    }

    public static function emergency(mixed $message, array $context = []): void
    {
        static::write('emergency', $message, $context);
    }

    public static function alert(mixed $message, array $context = []): void
    {
        static::write('alert', $message, $context);
    }

    public static function critical(mixed $message, array $context = []): void
    {
        static::write('critical', $message, $context);
    }

    public static function error(mixed $message, array $context = []): void
    {
        static::write('error', $message, $context);
    }

    public static function warning(mixed $message, array $context = []): void
    {
        static::write('warning', $message, $context);
    }

    public static function notice(mixed $message, array $context = []): void
    {
        static::write('notice', $message, $context);
    }

    public static function info(mixed $message, array $context = []): void
    {
        static::write('info', $message, $context);
    }

    public static function debug(mixed $message, array $context = []): void
    {
        static::write('debug', $message, $context);
    }

    public static function write(string $level, mixed $message, array $context = []): void
    {
        static::writeToChannel(null, $level, $message, $context);
    }

    public static function writeToChannel(?string $channelName, string $level, mixed $message, array $context = []): void
    {
        $timestamp = new DateTimeImmutable('now');
        $config = static::configuration();
        $channels = is_array($config['channels'] ?? null) ? $config['channels'] : [];
        $targetChannel = $channelName !== null && trim($channelName) !== ''
            ? trim($channelName)
            : (string) ($config['default'] ?? 'file');
        $record = Formatter::record($timestamp, $level, $message, $context);

        $visited = [];
        $handled = static::dispatchToChannel(
            $targetChannel,
            $channels,
            $timestamp,
            $level,
            $record,
            $visited
        );

        if (!$handled) {
            $fallbackChannel = $channels['file'] ?? [];
            static::writeFile($record, $fallbackChannel);
        }
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

    protected static function writeFile(array $record, array $channel): void
    {
        $directory = static::resolveDirectory($channel['path'] ?? null);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            return;
        }

        $logFile = $directory . '/' . $record['timestamp']->format('Y-m-d') . '.log';
        file_put_contents($logFile, $record['plain'], FILE_APPEND);
    }

    protected static function writeDatabase(array $record, array $channel): bool
    {
        if (!class_exists(LogRepository::class)) {
            return false;
        }

        try {
            $repository = new LogRepository($channel['table'] ?? 'logs');

            return $repository->store(
                $record['level'],
                Formatter::stringify($record['message']),
                $record['context'],
                $record['timestamp']->format('Y-m-d H:i:s')
            );
        } catch (\Throwable $e) {
            error_log('Log database write failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $channels
     * @param array<string, bool> $visited
     */
    protected static function dispatchToChannel(
        string $channelName,
        array $channels,
        DateTimeImmutable $timestamp,
        string $level,
        array $record,
        array &$visited
    ): bool {
        $channelName = trim($channelName);

        if ($channelName === '') {
            return false;
        }

        if (isset($visited[$channelName])) {
            return false;
        }
        $visited[$channelName] = true;

        if (!isset($channels[$channelName]) || !is_array($channels[$channelName])) {
            if ($channelName !== 'file' && isset($channels['file'])) {
                return static::dispatchToChannel(
                    'file',
                    $channels,
                    $timestamp,
                    $level,
                    $record,
                    $visited
                );
            }

            static::writeFile($record, []);

            return true;
        }

        $channel = $channels[$channelName];
        $driver = $channel['driver'] ?? $channelName;
        $handled = false;

        switch ($driver) {
            case 'stack':
                $handled = false;
                foreach ((array) ($channel['channels'] ?? []) as $nested) {
                    if (!is_string($nested) || trim($nested) === '') {
                        continue;
                    }

                    $handled = static::dispatchToChannel(
                        $nested,
                        $channels,
                        $timestamp,
                        $level,
                        $record,
                        $visited
                    ) || $handled;
                }
                break;

            case 'database':
                $handled = static::writeDatabase($record, $channel);
                break;

            case 'custom':
                $handled = static::writeCustom($record, $channel);
                break;

            case 'stream':
            case 'stderr':
                $useCli = ($driver === 'stderr');
                $handled = static::writeStream($record, $channel, $useCli);
                break;

            case 'file':
            default:
                static::writeFile($record, $channel);
                $handled = true;
                break;
        }

        if ($handled) {
            return true;
        }

        $fallback = $channel['fallback'] ?? null;
        if (is_string($fallback) && $fallback !== '' && $fallback !== $channelName) {
            return static::dispatchToChannel(
                $fallback,
                $channels,
                $timestamp,
                $level,
                $record,
                $visited
            );
        }

        if ($channelName !== 'file' && isset($channels['file'])) {
            return static::dispatchToChannel(
                'file',
                $channels,
                $timestamp,
                $level,
                $record,
                $visited
            );
        }

        return false;
    }

    protected static function writeStream(array $record, array $channel, bool $useCli = false): bool
    {
        if (defined('STDERR')) {
            @fwrite(STDERR, $useCli ? $record['cli'] : $record['plain']);

            return true;
        }

        $target = $channel['stream'] ?? 'php://stderr';
        $handle = @fopen($target, 'ab');

        if (is_resource($handle)) {
            $payload = $useCli
                ? preg_replace('/\x1b\[[0-9;]*m/', '', $record['cli'])
                : $record['plain'];
            @fwrite($handle, $payload ?? $record['plain']);
            fclose($handle);

            return true;
        }

        return error_log($record['plain']);
    }

    protected static function writeCustom(array $record, array $channel): bool
    {
        $handler = $channel['handler'] ?? null;

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            if (class_exists($class)) {
                $with = $channel['with'] ?? $channel['constructor'] ?? [];
                $with = is_array($with) ? array_values($with) : [$with];
                $instance = new $class(...$with);
                $handler = [$instance, $method];
            }
        } elseif (is_string($handler) && class_exists($handler)) {
            $with = $channel['with'] ?? $channel['constructor'] ?? [];
            $with = is_array($with) ? array_values($with) : [$with];
            $handler = new $handler(...$with);
        } elseif (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && class_exists($handler[0])) {
            $with = $channel['with'] ?? $channel['constructor'] ?? [];
            $with = is_array($with) ? array_values($with) : [$with];
            $class = $handler[0];
            $instance = new $class(...$with);
            $handler = [$instance, $handler[1]];
        }

        if ($handler instanceof LogHandlerInterface) {
            $handler->handle(
                $record['timestamp'],
                $record['level'],
                $record['message'],
                $record['context'],
                $record,
                $channel
            );

            return true;
        }

        if (is_callable($handler)) {
            $handler(
                $record['timestamp'],
                $record['level'],
                $record['message'],
                $record['context'],
                $record,
                $channel
            );

            return true;
        }

        return false;
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
                    'driver' => 'file',
                    'path' => null,
                ],
            ],
        ];

        return $config;
    }
}
