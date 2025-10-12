<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use FilesystemIterator;
use Zero\Lib\Console\Command\CommandInterface;
use function config;
use function storage_path;

/**
 * Built-in command to prune log files from the configured log directory.
 */
final class LogClearCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'log:clear';
    }

    public function getDescription(): string
    {
        return 'Remove generated *.log files for the configured channel or path.';
    }

    public function getUsage(): string
    {
        return 'php zero log:clear [--channel=file] [--path=/absolute/log/path]';
    }

    public function execute(array $argv): int
    {
        [, $options] = $this->parseOptions($argv);

        $pathOption = $options['path'] ?? null;
        $targetPath = is_string($pathOption) && trim($pathOption) !== '' ? $pathOption : null;

        if ($targetPath === null) {
            $channelOption = $options['channel'] ?? null;
            $channel = is_string($channelOption) && trim($channelOption) !== ''
                ? $channelOption
                : (string) config('logging.default', 'file');

            $channelConfig = (array) config('logging.channels.' . $channel, []);
            $targetPath = (string) ($channelConfig['path'] ?? storage_path('framework/logs'));
        }

        $targetPath = $this->normalisePath($targetPath);

        if (!is_dir($targetPath)) {
            fwrite(STDERR, sprintf('Log directory [%s] does not exist.%s', $targetPath, PHP_EOL));
            return 1;
        }

        $removed = 0;
        $iterator = new FilesystemIterator($targetPath, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if (! $item->isFile() || !str_ends_with($item->getFilename(), '.log')) {
                continue;
            }

            if (@unlink($item->getPathname())) {
                $removed++;
            }
        }

        if ($removed === 0) {
            echo sprintf('No log files found in %s%s', $targetPath, PHP_EOL);
        } else {
            echo sprintf('Removed %d log file(s) from %s%s', $removed, $targetPath, PHP_EOL);
        }

        return 0;
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    private function parseOptions(array $argv): array
    {
        $arguments = [];
        $options = [];
        $count = count($argv);

        for ($i = 2; $i < $count; $i++) {
            $token = $argv[$i];

            if ($token === '--') {
                $arguments = array_merge($arguments, array_slice($argv, $i + 1));
                break;
            }

            if (str_starts_with($token, '--')) {
                $segment = substr($token, 2);
                if ($segment === '') {
                    continue;
                }

                if (str_contains($segment, '=')) {
                    [$key, $value] = explode('=', $segment, 2);
                } else {
                    $key = $segment;
                    if ($i + 1 < $count && !str_starts_with((string) $argv[$i + 1], '-')) {
                        $value = $argv[++$i];
                    } else {
                        $value = true;
                    }
                }

                $options[$key] = $value;
                continue;
            }

            if (str_starts_with($token, '-')) {
                $flags = substr($token, 1);
                if ($flags === '') {
                    continue;
                }

                foreach (str_split($flags) as $flag) {
                    $options[$flag] = true;
                }

                continue;
            }

            $arguments[] = $token;
        }

        return [$arguments, $options];
    }

    private function normalisePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return $path;
        }

        if ($path[0] === '~') {
            $home = getenv('HOME') ?: getenv('USERPROFILE');
            if ($home !== false && $home !== '') {
                $path = $home . DIRECTORY_SEPARATOR . ltrim(substr($path, 1), DIRECTORY_SEPARATOR);
            }
        }

        if ($this->isAbsolutePath($path)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return rtrim(getcwd() . DIRECTORY_SEPARATOR . $path, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':');
    }
}
