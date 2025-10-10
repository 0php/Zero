<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Zero\Lib\Console\Application;
use Zero\Lib\Console\Command\CommandInterface;

final class ServeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'serve';
    }

    public function getDescription(): string
    {
        return 'Start the development server';
    }

    public function getUsage(): string
    {
        return 'php zero serve [--host=127.0.0.1] [--port=8000] [--root=public] [--watch] [--franken] [--swolee]';
    }

    public function execute(array $argv): int
    {
        $options = getopt('', [
            'host:',
            'port:',
            'root:',
            'franken',
            'swolee',
            'watch',
        ]);
        $options = $options === false ? [] : $options;

        $host = $options['host'] ?? env('HOST', Application::DEFAULT_HOST);
        $port = $options['port'] ?? env('PORT', Application::DEFAULT_PORT);
        $root = $options['root'] ?? env('DOCROOT', Application::DEFAULT_DOCROOT);
        $watch = array_key_exists('watch', $options ?? []);

        if (! is_dir($root)) {
            \Zero\Lib\Log::channel('internal')->error("Error: The specified document root \"{$root}\" does not exist.");
            return 1;
        }

        if (array_key_exists('franken', $options ?? [])) {
            $this->startFrankenServer($host, $port, $root, $watch);

            return 0;
        }

        if (array_key_exists('swolee', $options ?? [])) {
            if (! extension_loaded('swoole')) {
                \Zero\Lib\Log::channel('internal')->error('Error: The Swoole extension is not installed.');

                return 1;
            }

            $this->startSwooleServer($host, $port, $root, $watch);

            return 0;
        }

        \Zero\Lib\Log::channel('internal')->info('Starting PHP server in default mode...');
        $this->startPhpServer($host, $port, $root, $watch);

        return 0;
    }

    private function startPhpServer(string $host, string $port, string $root, bool $watch): void
    {
        if ($watch) {
            $this->startWatch($root, $host, $port);
        }

        $address = escapeshellarg($host . ':' . $port);
        $command = sprintf('php -S %s -t %s', $address, escapeshellarg($root));

        if (! $this->runCommand($command)) {
            \Zero\Lib\Log::channel('internal')->error(
                'Error: Unable to execute the PHP built-in server. Ensure passthru, system, exec, or shell_exec is enabled.'
            );
        }
    }

    private function startFrankenServer(string $host, string $port, string $root, bool $watch): void
    {
        if ($watch) {
            $this->startWatch($root, $host, $port);
        }

        \Zero\Lib\Log::channel('internal')->info('Running Franken server...');
        \Zero\Lib\Log::channel('internal')->info("Host: {$host}, Port: {$port}, Document Root: {$root}");
        \Zero\Lib\Log::channel('internal')->info('Franken mode started...');
    }

    private function startSwooleServer(string $host, string $port, string $root, bool $watch): void
    {
        if ($watch) {
            $this->startWatch($root, $host, $port);
        }

        $server = new \Swoole\Http\Server($host, (int) $port);

        $server->on('Request', function ($request, $response) use ($root) {
            $file = $root . $request->server['request_uri'];
            if (file_exists($file)) {
                $response->header('Content-Type', mime_content_type($file) ?: 'text/plain');
                $response->send(file_get_contents($file) ?: '');
            } else {
                $response->status(404);
                $response->end('Not Found');
            }
        });

        \Zero\Lib\Log::channel('internal')->info("Swoole server started at http://{$host}:{$port}...");
        $server->start();
    }

    private function startWatch(string $directory, string $host, string $port): void
    {
        \Zero\Lib\Log::channel('internal')->info("Watching for file changes in {$directory}...");
        $address = escapeshellarg($host . ':' . $port);
        $command = sprintf('php -S %s -t %s', $address, escapeshellarg($directory));

        if (function_exists('inotify_init')) {
            $inotify = inotify_init();
            stream_set_blocking($inotify, false);
            inotify_add_watch($inotify, $directory, IN_MODIFY | IN_CREATE | IN_DELETE);

            while (true) {
                $events = inotify_read($inotify);
                if (! empty($events)) {
                    \Zero\Lib\Log::channel('internal')->info('File change detected, restarting server...');
                    $this->runCommand($command);
                }
                usleep(500000);
            }
        }

        $lastModified = $this->getLastModifiedTime($directory);
        while (true) {
            clearstatcache();
            $current = $this->getLastModifiedTime($directory);
            if ($current > $lastModified) {
                $lastModified = $current;
                \Zero\Lib\Log::channel('internal')->info('File change detected, restarting server...');
                $this->runCommand($command);
            }
            usleep(500000);
        }
    }

    private function getLastModifiedTime(string $directory): int
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        $lastModified = 0;

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $lastModified = max($lastModified, $file->getMTime());
            }
        }

        return $lastModified;
    }

    private function runCommand(string $command): bool
    {
        if (\function_exists('passthru')) {
            $status = 0;
            \passthru($command, $status);

            return true;
        }

        if (\function_exists('system')) {
            $status = 0;
            \system($command, $status);

            return true;
        }

        if (\function_exists('exec')) {
            $output = [];
            $status = 0;
            \exec($command, $output, $status);

            if ($output !== []) {
                echo implode(PHP_EOL, $output) . PHP_EOL;
            }

            return true;
        }

        if (\function_exists('shell_exec')) {
            $output = \shell_exec($command);

            if ($output !== null) {
                echo $output;

                return true;
            }
        }

        return false;
    }
}
