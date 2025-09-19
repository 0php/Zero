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

        $host = $options['host'] ?? Application::DEFAULT_HOST;
        $port = $options['port'] ?? Application::DEFAULT_PORT;
        $root = $options['root'] ?? Application::DEFAULT_DOCROOT;
        $watch = array_key_exists('watch', $options ?? []);

        if (! is_dir($root)) {
            fwrite(STDERR, "Error: The specified document root \"{$root}\" does not exist.\n");
            return 1;
        }

        if (array_key_exists('franken', $options ?? [])) {
            $this->startFrankenServer($host, $port, $root, $watch);

            return 0;
        }

        if (array_key_exists('swolee', $options ?? [])) {
            if (! extension_loaded('swoole')) {
                fwrite(STDERR, "Error: The Swoole extension is not installed.\n");

                return 1;
            }

            $this->startSwooleServer($host, $port, $root, $watch);

            return 0;
        }

        fwrite(STDOUT, "Starting PHP server in default mode...\n");
        $this->startPhpServer($host, $port, $root, $watch);

        return 0;
    }

    private function startPhpServer(string $host, string $port, string $root, bool $watch): void
    {
        if ($watch) {
            $this->startWatch($root, $host, $port);
        }

        $command = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($root)
        );

        passthru($command);
    }

    private function startFrankenServer(string $host, string $port, string $root, bool $watch): void
    {
        if ($watch) {
            $this->startWatch($root, $host, $port);
        }

        fwrite(STDOUT, "Running Franken server...\n");
        fwrite(STDOUT, "Host: {$host}, Port: {$port}, Document Root: {$root}\n");
        fwrite(STDOUT, "Franken mode started...\n");
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

        fwrite(STDOUT, "Swoole server started at http://{$host}:{$port}...\n");
        $server->start();
    }

    private function startWatch(string $directory, string $host, string $port): void
    {
        fwrite(STDOUT, "Watching for file changes in {$directory}...\n");
        $command = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($directory)
        );

        if (function_exists('inotify_init')) {
            $inotify = inotify_init();
            stream_set_blocking($inotify, false);
            inotify_add_watch($inotify, $directory, IN_MODIFY | IN_CREATE | IN_DELETE);

            while (true) {
                $events = inotify_read($inotify);
                if (! empty($events)) {
                    fwrite(STDOUT, "File change detected, restarting server...\n");
                    exec($command);
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
                fwrite(STDOUT, "File change detected, restarting server...\n");
                exec($command);
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
}
