<?php

declare(strict_types=1);

namespace Zero\Lib\Log;

use DateTimeImmutable;

final class Formatter
{
    /**
     * Build a normalized log record containing different renderings.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function record(DateTimeImmutable $timestamp, string $level, mixed $message, array $context): array
    {
        $normalizedLevel = strtolower($level);
        $cleanContext = self::normalizeContext($context);

        $record = [
            'timestamp' => $timestamp,
            'level' => $normalizedLevel,
            'level_upper' => strtoupper($normalizedLevel),
            'message' => $message,
            'context' => $cleanContext,
        ];

        $record['plain'] = self::formatPlain($record);
        $record['cli'] = self::formatCli($record);

        return $record;
    }

    /**
     * Produce a simple single-line string for files/databases.
     *
     * @param array<string, mixed> $record
     */
    public static function formatPlain(array $record): string
    {
        $prefix = '[' . $record['timestamp']->format('Y-m-d H:i:s') . '] ' . $record['level_upper'];
        $message = is_string($record['message'])
            ? $record['message']
            : self::stringify($record['message']);

        if (!empty($record['context'])) {
            $context = self::stringify($record['context']);
            $message .= ' ' . $context;
        }

        return $prefix . ': ' . $message . PHP_EOL;
    }

    /**
     * Render a colourful CLI representation similar to dd().
     *
     * @param array<string, mixed> $record
     */
    public static function formatCli(array $record): string
    {
        if (($record['level'] ?? '') !== 'debug') {
            return $record['plain'] ?? self::formatPlain($record);
        }

        $colors = self::cliPalette();
        $width = self::cliWidth();

        $lines = [];
        $rule = self::color($colors, 'muted', str_repeat('═', $width));
        $lines[] = $rule;

        $header = sprintf(
            '%s %s %s %s',
            self::color($colors, 'muted', '[' . $record['timestamp']->format('H:i:s') . ']'),
            self::levelColor($colors, $record['level_upper']),
            self::color($colors, 'muted', 'PID'),
            self::color($colors, 'fg', (string) getmypid())
        );
        $lines[] = self::truncateCli($header, $width);

        $memory = sprintf(
            '%s %s %s %s',
            self::color($colors, 'muted', 'Mem'),
            self::color($colors, 'fg', self::formatBytes(memory_get_usage(true))),
            self::color($colors, 'muted', 'Peak'),
            self::color($colors, 'fg', self::formatBytes(memory_get_peak_usage(true)))
        );
        $lines[] = self::truncateCli($memory, $width);
        $lines[] = self::color($colors, 'muted', str_repeat('─', $width));

        $lines = array_merge($lines, self::indentBlock(self::dump($record['message'], $colors), $colors));

        if (!empty($record['context'])) {
            $lines[] = self::color($colors, 'cyan', 'Context');
            $lines = array_merge($lines, self::indentBlock(self::dump($record['context'], $colors), $colors));
        }

        $lines[] = $rule;

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * Convert any value into a printable string (no colours).
     */
    public static function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return self::dump($value, null);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $normalized[$key] = is_scalar($value) || $value === null
                ? $value
                : self::stringify($value);
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $colors
     */
    private static function indentBlock(string $text, array $colors): array
    {
        $lines = preg_split('/\R/', $text) ?: [];

        return array_map(
            static fn (string $line): string => '  ' . $line,
            $lines
        );
    }

    private static function cliWidth(): int
    {
        $columns = getenv('COLUMNS');
        if (is_string($columns) && trim($columns) !== '' && (int) $columns > 0) {
            return max(60, min((int) $columns, 160));
        }

        $reported = @exec('tput cols');
        if (is_string($reported) && trim($reported) !== '' && (int) $reported > 0) {
            return max(60, min((int) $reported, 160));
        }

        return 100;
    }

    /**
     * Dump a value either with colours (for CLI) or plain text.
     */
    private static function dump(mixed $value, ?array $colors, int $depth = 0, ?array &$seen = null): string
    {
        if ($seen === null) {
            $seen = [];
        }
        $indent = str_repeat('    ', $depth);
        $nextIndent = str_repeat('    ', $depth + 1);

        if ($depth > 6) {
            return self::color($colors, 'muted', '… (max depth)');
        }

        if ($value === null) {
            return self::color($colors, 'purple', 'null');
        }

        if (is_bool($value)) {
            return self::color($colors, 'purple', $value ? 'true' : 'false');
        }

        if (is_int($value) || is_float($value)) {
            return self::color($colors, 'purple', (string) $value);
        }

        if (is_string($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                $encoded = addslashes($value);
            }

            return self::color($colors, 'yellow', (string) $encoded);
        }

        if (is_resource($value) || gettype($value) === 'resource (closed)') {
            return self::color($colors, 'cyan', 'resource(' . get_resource_type($value) . ')');
        }

        if (is_array($value)) {
            if ($value === []) {
                return self::color($colors, 'muted', '[]');
            }

            $lines = [self::color($colors, 'muted', '[')];
            foreach ($value as $key => $item) {
                $keyStr = is_int($key)
                    ? self::color($colors, 'green', (string) $key)
                    : self::color($colors, 'yellow', json_encode((string) $key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                $lines[] = $nextIndent . $keyStr . self::color($colors, 'muted', ' => ') . self::dump($item, $colors, $depth + 1, $seen);
            }
            $lines[] = $indent . self::color($colors, 'muted', ']');

            return implode(PHP_EOL, $lines);
        }

        if (is_object($value)) {
            $objectId = spl_object_id($value);
            if (isset($seen[$objectId])) {
                return self::color($colors, 'muted', get_class($value) . '#' . $objectId . ' {… recursion}');
            }
            $seen[$objectId] = true;

            $lines = [self::color($colors, 'cyan', get_class($value)) . self::color($colors, 'muted', ' #' . $objectId . ' {')];

            try {
                $reflection = new \ReflectionObject($value);
                $properties = $reflection->getProperties();
            } catch (\Throwable $e) {
                return self::color($colors, 'muted', get_class($value) . ' { /* reflection error: ' . $e->getMessage() . ' */ }');
            }

            if ($properties === []) {
                $lines[] = $nextIndent . self::color($colors, 'muted', '// no properties');
            } else {
                foreach ($properties as $property) {
                    $property->setAccessible(true);
                    $visibility = $property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private');
                    $label = self::color($colors, 'blue', $visibility) . ' ' . self::color($colors, 'yellow', '$' . $property->getName());

                    try {
                        $lines[] = $nextIndent . $label . self::color($colors, 'muted', ' = ') . self::dump($property->getValue($value), $colors, $depth + 1, $seen);
                    } catch (\Throwable $e) {
                        $lines[] = $nextIndent . $label . self::color($colors, 'muted', ' = /* inaccessible: ' . $e->getMessage() . ' */');
                    }
                }
            }

            $lines[] = $indent . self::color($colors, 'muted', '}');

            unset($seen[$objectId]);

            return implode(PHP_EOL, $lines);
        }

        return self::color($colors, 'muted', var_export($value, true));
    }

    /**
     * @return array<string, string>
     */
    private static function cliPalette(): array
    {
        if (!self::supportsTruecolor()) {
            return [
                'reset' => "\033[0m",
                'fg' => "\033[37m",
                'muted' => "\033[90m",
                'red' => "\033[31m",
                'green' => "\033[32m",
                'yellow' => "\033[33m",
                'blue' => "\033[34m",
                'purple' => "\033[35m",
                'cyan' => "\033[36m",
            ];
        }

        return [
            'reset' => "\033[0m",
            'fg' => self::rgb(248, 248, 242),
            'muted' => self::rgb(117, 113, 94),
            'red' => self::rgb(249, 38, 114),
            'green' => self::rgb(166, 226, 46),
            'yellow' => self::rgb(230, 219, 116),
            'blue' => self::rgb(102, 217, 239),
            'purple' => self::rgb(174, 129, 255),
            'cyan' => self::rgb(102, 217, 239),
        ];
    }

    private static function levelColor(array $colors, string $level): string
    {
        $map = [
            'EMERGENCY' => 'red',
            'ALERT' => 'red',
            'CRITICAL' => 'red',
            'ERROR' => 'red',
            'WARNING' => 'yellow',
            'NOTICE' => 'blue',
            'INFO' => 'green',
            'DEBUG' => 'muted',
        ];

        $color = $map[$level] ?? 'fg';

        return self::color($colors, $color, $level);
    }

    private static function color(?array $colors, string $key, string $text): string
    {
        if ($colors === null) {
            return $text;
        }

        $code = $colors[$key] ?? ($colors['fg'] ?? '');
        $reset = $colors['reset'] ?? '';

        return $code . $text . $reset;
    }

    private static function rgb(int $r, int $g, int $b): string
    {
        return sprintf("\033[38;2;%d;%d;%dm", $r, $g, $b);
    }

    private static function supportsTruecolor(): bool
    {
        $colorterm = getenv('COLORTERM');
        if (is_string($colorterm) && (stripos($colorterm, 'truecolor') !== false || stripos($colorterm, '24bit') !== false)) {
            return true;
        }

        $term = getenv('TERM');
        return is_string($term) && stripos($term, 'truecolor') !== false;
    }

    private static function truncateCli(string $line, int $width): string
    {
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $line) ?? $line;
        if (strlen($plain) <= $width) {
            return $line;
        }

        $visible = substr($plain, 0, $width - 1) . '…';
        return substr($line, 0, strlen($line) - strlen($plain) + strlen($visible)) . '…';
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes = (int) round($bytes / 1024);
            $i++;
        }

        return $bytes . ' ' . $units[$i];
    }
}
