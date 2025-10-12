<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use function config;
use function storage_path;

final class DatabaseRestoreCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'db:restore';
    }

    public function getDescription(): string
    {
        return 'Restore the configured database from an SQL dump file.';
    }

    public function getUsage(): string
    {
        return 'php zero db:restore [--connection=mysql] [--file=storage/database/dumps/backup.sql]';
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
        $userProvided = is_string($fileOption) && trim($fileOption) !== '';

        if ($userProvided) {
            $file = $this->normalisePath(trim((string) $fileOption));

            if (!is_file($file)) {
                fwrite(STDERR, sprintf('Dump file [%s] does not exist.%s', $file, PHP_EOL));
                return 1;
            }
        } else {
            $file = $this->latestDump(storage_path('database/dumps'));

            if ($file === null) {
                fwrite(STDERR, 'No dump file found. Use --file to specify one.' . PHP_EOL);
                return 1;
            }

            echo sprintf('Restoring from latest dump: %s%s', $file, PHP_EOL);
        }

        return match ($driver) {
            'mysql' => $this->restoreMysql($config, $file),
            'pgsql', 'postgres' => $this->restorePostgres($config, $file),
            'sqlite', 'sqlite3' => $this->restoreSqlite($config, $file),
            default => $this->unsupportedDriver($driver),
        };
    }

    private function restoreMysql(array $config, string $file): int
    {
        $binary = $this->findBinary('mysql');

        if ($binary === null) {
            fwrite(STDERR, 'mysql client binary was not found in PATH.' . PHP_EOL);
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
            '%s --host=%s --port=%s --user=%s %s < %s',
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

        exec($command, $output, $exitCode);

        if ($password !== '') {
            if ($previous === false) {
                putenv('MYSQL_PWD');
            } else {
                putenv('MYSQL_PWD=' . $previous);
            }
        }

        if ($exitCode !== 0) {
            fwrite(STDERR, sprintf('mysql exited with status %d.%s', $exitCode, PHP_EOL));
            return $exitCode ?: 1;
        }

        echo sprintf('Database restored from %s%s', $file, PHP_EOL);

        return 0;
    }

    private function restorePostgres(array $config, string $file): int
    {
        $binary = $this->findBinary('psql');

        if ($binary === null) {
            fwrite(STDERR, 'psql binary was not found in PATH.' . PHP_EOL);
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
            '%s --host=%s --port=%s --username=%s --dbname=%s < %s',
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

        exec($command, $output, $exitCode);

        if ($password !== '') {
            if ($previous === false) {
                putenv('PGPASSWORD');
            } else {
                putenv('PGPASSWORD=' . $previous);
            }
        }

        if ($exitCode !== 0) {
            fwrite(STDERR, sprintf('psql exited with status %d.%s', $exitCode, PHP_EOL));
            return $exitCode ?: 1;
        }

        echo sprintf('Database restored from %s%s', $file, PHP_EOL);

        return 0;
    }

    private function restoreSqlite(array $config, string $file): int
    {
        $databasePath = (string) ($config['database'] ?? '');

        if ($databasePath === '') {
            fwrite(STDERR, 'SQLite database path is not configured.' . PHP_EOL);
            return 1;
        }

        if (!@copy($file, $databasePath)) {
            fwrite(STDERR, sprintf('Unable to copy dump [%s] to [%s].%s', $file, $databasePath, PHP_EOL));
            return 1;
        }

        echo sprintf('SQLite database restored from %s%s', $file, PHP_EOL);

        return 0;
    }

    private function latestDump(string $directory): ?string
    {
        if (!is_dir($directory)) {
            return null;
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*');

        if (!is_array($files) || $files === []) {
            return null;
        }

        rsort($files, SORT_STRING);

        return $files[0] ?? null;
    }

    private function unsupportedDriver(string $driver): int
    {
        fwrite(STDERR, sprintf('Database driver [%s] is not supported by db:restore.%s', $driver, PHP_EOL));
        return 1;
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
