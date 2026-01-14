<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use RuntimeException;
use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Support\DateTime;
use function config;
use function storage_path;

final class DatabaseDumpCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'db:dump';
    }

    public function getDescription(): string
    {
        return 'Export the configured database to an SQL dump file.';
    }

    public function getUsage(): string
    {
        return 'php zero db:dump [--connection=mysql] [--file=storage/database/dumps/backup.sql]';
    }

    public function execute(array $argv): int
    {
        [, $options] = $this->parseOptions($argv);

        $connectionOption = $options['connection'] ?? null;
        $connection = is_string($connectionOption) && trim($connectionOption) !== ''
            ? $connectionOption
            : (string) config('database.connection', 'mysql');

        $config = (array) config('database.' . $connection, []);

        if ($config === []) {
            fwrite(STDERR, sprintf('Database connection [%s] is not defined.%s', $connection, PHP_EOL));
            return 1;
        }

        $driver = (string) ($config['driver'] ?? $connection);

        $fileOption = $options['file'] ?? null;
        $file = is_string($fileOption) ? trim($fileOption) : '';

        if ($file === '') {
            $dumpDir = storage_path('database/dumps');
            $this->ensureDirectory($dumpDir);
            $file = $dumpDir . DIRECTORY_SEPARATOR . sprintf('%s-%s.sql', $connection, DateTime::now()->format('Ymd-His'));
        } else {
            $file = $this->normalisePath($file);
            $this->ensureDirectory(dirname($file));
        }

        return match ($driver) {
            'mysql' => $this->dumpMysql($config, $file),
            'pgsql', 'postgres' => $this->dumpPostgres($config, $file),
            'sqlite', 'sqlite3' => $this->dumpSqlite($config, $file),
            default => $this->unsupportedDriver($driver),
        };
    }

    private function dumpMysql(array $config, string $file): int
    {
        $binary = $this->findBinary('mysqldump');

        if ($binary === null) {
            fwrite(STDERR, 'mysqldump binary was not found in PATH.' . PHP_EOL);
            return 1;
        }

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '3306');
        $username = (string) ($config['username'] ?? 'root');
        $database = (string) ($config['database'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($database === '') {
            fwrite(STDERR, 'MySQL database name is not configured.' . PHP_EOL);
            return 1;
        }

        $command = sprintf(
            '%s --host=%s --port=%s --user=%s --single-transaction --quick --lock-tables=false %s > %s',
            escapeshellcmd($binary),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($file)
        );

        $previous = getenv('MYSQL_PWD');
        if ($password !== '') {
            putenv('MYSQL_PWD=' . $password);
        }

        $exitCode = $this->runCommand($command, $output);

        if ($password !== '') {
            if ($previous === false) {
                putenv('MYSQL_PWD');
            } else {
                putenv('MYSQL_PWD=' . $previous);
            }
        }

        if ($exitCode !== 0) {
            fwrite(STDERR, sprintf('mysqldump exited with status %d.%s', $exitCode, PHP_EOL));
            return $exitCode ?: 1;
        }

        echo sprintf('Database dump written to %s%s', $file, PHP_EOL);

        return 0;
    }

    private function dumpPostgres(array $config, string $file): int
    {
        $binary = $this->findBinary('pg_dump');

        if ($binary === null) {
            fwrite(STDERR, 'pg_dump binary was not found in PATH.' . PHP_EOL);
            return 1;
        }

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (string) ($config['port'] ?? '5432');
        $username = (string) ($config['username'] ?? 'postgres');
        $database = (string) ($config['database'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($database === '') {
            fwrite(STDERR, 'PostgreSQL database name is not configured.' . PHP_EOL);
            return 1;
        }

        $command = sprintf(
            '%s --host=%s --port=%s --username=%s --no-owner --no-privileges %s > %s',
            escapeshellcmd($binary),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($file)
        );

        $previous = getenv('PGPASSWORD');
        if ($password !== '') {
            putenv('PGPASSWORD=' . $password);
        }

        $exitCode = $this->runCommand($command, $output);

        if ($password !== '') {
            if ($previous === false) {
                putenv('PGPASSWORD');
            } else {
                putenv('PGPASSWORD=' . $previous);
            }
        }

        if ($exitCode !== 0) {
            fwrite(STDERR, sprintf('pg_dump exited with status %d.%s', $exitCode, PHP_EOL));
            return $exitCode ?: 1;
        }

        echo sprintf('Database dump written to %s%s', $file, PHP_EOL);

        return 0;
    }

    private function dumpSqlite(array $config, string $file): int
    {
        $databasePath = (string) ($config['database'] ?? '');

        if ($databasePath === '') {
            fwrite(STDERR, 'SQLite database path is not configured.' . PHP_EOL);
            return 1;
        }

        if (!is_file($databasePath)) {
            fwrite(STDERR, sprintf('SQLite database file [%s] does not exist.%s', $databasePath, PHP_EOL));
            return 1;
        }

        if (!@copy($databasePath, $file)) {
            fwrite(STDERR, sprintf('Unable to copy SQLite database from [%s] to [%s].%s', $databasePath, $file, PHP_EOL));
            return 1;
        }

        echo sprintf('SQLite database copied to %s%s', $file, PHP_EOL);

        return 0;
    }

    private function unsupportedDriver(string $driver): int
    {
        fwrite(STDERR, sprintf('Database driver [%s] is not supported by db:dump.%s', $driver, PHP_EOL));
        return 1;
    }

    private function runCommand(string $command, ?array &$output = null): int
    {
        $output = [];

        if (function_exists('exec')) {
            exec($command, $output, $exitCode);
            return $exitCode;
        }

        if (!function_exists('shell_exec')) {
            fwrite(STDERR, 'Shell execution functions are disabled.' . PHP_EOL);
            return 1;
        }

        $marker = '__ZERO_EXIT_CODE__';
        $result = shell_exec($command . '; printf "\n' . $marker . '%s" $?');

        if ($result === null) {
            fwrite(STDERR, 'Shell execution failed.' . PHP_EOL);
            return 1;
        }

        $parts = explode("\n" . $marker, $result, 2);
        $outputText = $parts[0] ?? '';
        $exitCode = (int) ($parts[1] ?? 1);

        $output = $outputText === '' ? [] : preg_split("/\r\n|\n|\r/", rtrim($outputText));

        return $exitCode;
    }

    private function ensureDirectory(string $path): void
    {
        if ($path === '' || is_dir($path)) {
            return;
        }

        if (!@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create directory [%s].', $path));
        }
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
            return $path;
        }

        return getcwd() . DIRECTORY_SEPARATOR . $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':');
    }

    private function findBinary(string $binary): ?string
    {
        $candidates = [
            trim((string) @shell_exec('command -v ' . escapeshellcmd($binary))),
            trim((string) @shell_exec('which ' . escapeshellcmd($binary))),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
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
}
