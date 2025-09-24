<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Input;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Support\Str;
use Zero\Lib\Template;

final class MakeCommandCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:command';
    }

    public function getDescription(): string
    {
        return 'Generate a console command class';
    }

    public function getUsage(): string
    {
        return 'php zero make:command Name [--signature=app:example] [--description="..."] [--force]';
    }

    public function execute(array $argv, ?Input $input = null): int
    {
        $input ??= new Input([], []);

        $arguments = $input->arguments();
        $name = $arguments[0] ?? null;
        $force = (bool) $input->option('force', false);
        $signatureOption = $input->option('signature');
        $descriptionOption = $input->option('description');

        if ($name === null) {
            // Fall back to legacy argv parsing for backward compatibility
            $name = $argv[2] ?? null;
            $force = $force || in_array('--force', $argv, true);
            $signatureOption ??= $this->extractOption($argv, '--signature=');
            $descriptionOption ??= $this->extractOption($argv, '--description=');
        }

        if ($name === null) {
            \Zero\Lib\Log::channel('internal')->error("Usage: {$this->getUsage()}");

            return 1;
        }

        $name = str_replace('\\', '/', $name);
        $name = preg_replace('#/+#', '/', $name);
        $name = trim((string) $name, '/');

        if ($name === '') {
            \Zero\Lib\Log::channel('internal')->error('Invalid command name provided.');

            return 1;
        }

        $className = Str::studly(basename($name));
        $directory = dirname($name);
        $directory = $directory === '.' ? '' : trim($directory, '/') . '/';

        $path = app_path('console/Commands/' . $directory . $className . '.php');

        if (file_exists($path) && ! $force) {
            \Zero\Lib\Log::channel('internal')->error("Command {$className} already exists. Use --force to overwrite.");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $namespace = 'App\\Console\\Commands';
        if ($directory !== '') {
            $namespace .= '\\' . str_replace('/', '\\', rtrim($directory, '/'));
        }

        $signature = $signatureOption ?? 'app:' . Str::kebab($className);
        $description = $descriptionOption ?? 'Custom application command';

        $contents = Template::render('command.tmpl', [
            'namespace' => $namespace,
            'class' => $className,
            'signature' => $signature,
            'description' => $description,
        ]);

        file_put_contents($path, $contents);
        \Zero\Lib\Log::channel('internal')->info("Command created: {$path}");

        if ($this->ensureCommandRegistrar()) {
            $fullyQualified = '\\' . ltrim($namespace . '\\' . $className, '\\');
            if ($this->appendToRegistrar($fullyQualified)) {
                \Zero\Lib\Log::channel('internal')->info('Command registered in app/console/Commands/Command.php');
            } else {
                \Zero\Lib\Log::channel('internal')->error('Unable to update app/console/Commands/Command.php automatically.');
            }
        }

        return 0;
    }

    private function extractOption(array $arguments, string $prefix): ?string
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, $prefix)) {
                $value = substr($argument, strlen($prefix));
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    private function ensureCommandRegistrar(): bool
    {
        $registrarPath = app_path('console/Commands/Command.php');
        $legacyPath = app_path('console/Command.php');
        $existing = [];

        if (file_exists($registrarPath)) {
            return true;
        }

        if (file_exists($legacyPath)) {
            $existing = $this->extractCommandList($legacyPath);
        }

        Filesystem::ensureDirectory(dirname($registrarPath));

        $contents = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Zero\Lib\Console\Application;
use Zero\Lib\Support\RegistersConsoleCommands;

class Command
{
    use RegistersConsoleCommands;

    /**
     * Register application console commands.
     */
    public function boot(Application $app): void
    {
        $this->register($app, [
            // \App\Console\Commands\ExampleCommand::class,
        ]);
    }
}
PHP;

        if ($existing !== []) {
            $indent = '            ';
            $lines = array_map(static fn (string $entry): string => $indent . $entry . '::class,', $existing);
            $lines[] = $indent . '// \App\Console\Commands\ExampleCommand::class,';

            $contents = preg_replace(
                '/\$this->register\(\$app, \[\s*\/\/ \\App\\Console\\Commands\\ExampleCommand::class,\s*\]\);/',
                '$this->register($app, [' . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . '        ]);',
                $contents
            );
        }

        $result = file_put_contents($registrarPath, $contents) !== false;

        if ($result && file_exists($legacyPath)) {
            @unlink($legacyPath);
        }

        return $result;
    }

    private function appendToRegistrar(string $class): bool
    {
        $registrarPath = app_path('console/Commands/Command.php');

        if (! file_exists($registrarPath) || ! is_writable($registrarPath)) {
            return false;
        }

        $contents = file_get_contents($registrarPath);
        if ($contents === false) {
            return false;
        }

        if (str_contains($contents, $class . '::class')) {
            return true;
        }

        if (! preg_match('/\$this->register\(\$app,\s*\[(.*?)\]\);/s', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $block = $matches[1][0];
        $blockOffset = (int) $matches[1][1];

        $lines = preg_split('/\R/', trim($block)) ?: [];
        $entries = [];
        $comments = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '//')) {
                $comments[] = $trimmed;

                continue;
            }

            $entries[] = rtrim($trimmed, ',');
        }

        $entries[] = $class . '::class';
        $entries = array_unique($entries);
        $entries = array_values($entries);
        usort($entries, static function (string $a, string $b): int {
            $normalize = static fn (string $value): string => ltrim(preg_replace('/::class$/', '', $value) ?? $value, '\\');

            return strcmp($normalize($a), $normalize($b));
        });

        $indent = '            ';
        $rebuilt = [];
        foreach ($entries as $entry) {
            $rebuilt[] = $indent . $entry . ',';
        }

        foreach ($comments as $comment) {
            $rebuilt[] = $indent . $comment;
        }

        $replacement = PHP_EOL . implode(PHP_EOL, $rebuilt) . PHP_EOL . '        ';

        $updated = substr_replace(
            $contents,
            $replacement,
            $blockOffset,
            strlen($block)
        );

        $updated = preg_replace("/\n[ \t]+\n/", "\n", $updated) ?? $updated;

        return file_put_contents($registrarPath, $updated) !== false;
    }

    /**
     * @return array<int, string>
     */
    private function extractCommandList(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        if (! preg_match('/\$this->register(?:Commands)?\(\$app,\s*\[(.*?)\]\);/s', $contents, $matches)) {
            return [];
        }

        $block = $matches[1];
        $lines = preg_split('/\R/', $block) ?: [];
        $entries = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }

            $trimmed = rtrim($trimmed, ',');
            if ($trimmed !== '') {
                $entries[] = ltrim($trimmed, '\\');
            }
        }

        $entries = array_map(static fn (string $entry): string => '\\' . ltrim($entry, '\\'), $entries);
        $entries = array_unique($entries);
        sort($entries);

        return $entries;
    }
}
