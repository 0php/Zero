<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Router;

final class RouteListCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'route:list';
    }

    public function getDescription(): string
    {
        return 'Display the registered routes';
    }

    public function getUsage(): string
    {
        return 'php zero route:list';
    }

    public function execute(array $argv): int
    {
        $this->bootstrapRoutes();
        $routes = Router::getRoutes();

        if (empty($routes)) {
            \Zero\Lib\Log::channel('internal')->info('No routes have been registered.');

            return 0;
        }

        $rows = [];
        foreach ($routes as $route) {
            $rows[] = [
                $route['method'],
                $route['uri'],
                $route['name'] ?? '',
                $route['action'],
                implode(', ', array_map('strval', $route['middleware'])),
            ];
        }

        $headers = ['METHOD', 'URI', 'NAME', 'ACTION', 'MIDDLEWARE'];
        $widths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $widths[$index] = max($widths[$index], strlen($value));
            }
        }

        $line = function (array $columns) use ($widths): string {
            $segments = [];
            foreach ($columns as $index => $value) {
                $segments[] = str_pad($value, $widths[$index]);
            }

            return implode('  ', $segments);
        };

        \Zero\Lib\Log::channel('internal')->info($line($headers));
        \Zero\Lib\Log::channel('internal')->info(str_repeat('-', array_sum($widths) + (count($widths) - 1) * 2));

        foreach ($rows as $row) {
            \Zero\Lib\Log::channel('internal')->info($line($row));
        }

        \Zero\Lib\Log::channel('internal')->info(sprintf('Total: %d routes', count($rows)));

        return 0;
    }

    private function bootstrapRoutes(): void
    {
        $basePath = $_ENV['BASE_PATH'] ?? dirname(__DIR__, 4);
        $webRoutes = $basePath . '/routes/web.php';

        if (file_exists($webRoutes)) {
            require_once $webRoutes;
        }
    }
}
