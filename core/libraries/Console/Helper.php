<?php

declare(strict_types=1);

/**
 * Dump the given values and terminate execution using a Monokai-inspired theme
 * for both CLI and HTTP outputs. The helper stays framework-agnostic so it can
 * be used from anywhere without pulling additional dependencies.
 *
 * @param mixed ...$values
 */
if (!function_exists('dd')) {
    function dd(...$values): void
    {
        $origin = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];
        $context = [
            'file'   => $origin['file'] ?? 'unknown',
            'line'   => (int) ($origin['line'] ?? 0),
            'time'   => date('Y-m-d H:i:s'),
            'pid'    => getmypid(),
            'memory' => memory_get_usage(true),
            'peak'   => memory_get_peak_usage(true),
        ];

        if (PHP_SAPI === 'cli') {
            _ddRenderCli($values, $context);
        } else {
            _ddRenderHttp($values, $context);
        }

        exit(255);
    }
}

if (!function_exists('_ddRenderCli')) {
    /**
     * Render dumped values with a Monokai palette for CLI usage.
     *
     * @param array<int, mixed> $values
     * @param array<string, mixed> $context
     */
    function _ddRenderCli(array $values, array $context): void
    {
        $colors = _ddCliPalette();
        $columns = _ddCliColumns();
        $line = str_repeat('═', $columns);

        fwrite(STDOUT, _ddColor($colors, 'muted', $line) . PHP_EOL);
        fwrite(
            STDOUT,
            _ddColor($colors, 'muted', 'Source: ') .
            _ddColor($colors, 'green', _ddShortenPath((string) $context['file'])) .
            _ddColor($colors, 'muted', ':') .
            _ddColor($colors, 'purple', (string) $context['line']) . PHP_EOL
        );
        fwrite(
            STDOUT,
            _ddColor($colors, 'muted', 'Time:   ') .
            _ddColor($colors, 'fg', $context['time']) .
            _ddColor($colors, 'muted', '  PID: ') .
            _ddColor($colors, 'fg', (string) $context['pid']) .
            _ddColor($colors, 'muted', '  Mem: ') .
            _ddColor($colors, 'fg', _ddFormatBytes((int) $context['memory'])) .
            _ddColor($colors, 'muted', ' (peak ') .
            _ddColor($colors, 'fg', _ddFormatBytes((int) $context['peak'])) .
            _ddColor($colors, 'muted', ')') . PHP_EOL
        );

        fwrite(STDOUT, _ddColor($colors, 'muted', str_repeat('─', $columns)) . PHP_EOL);

        foreach ($values as $index => $value) {
            fwrite(
                STDOUT,
                _ddColor($colors, 'red', 'Variable #' . ($index + 1)) . ' ' .
                _ddColor($colors, 'blue', '(' . gettype($value) . ')') . PHP_EOL
            );

            $meta = _ddDescribeMeta($value);
            if ($meta !== '') {
                fwrite(STDOUT, _ddColor($colors, 'muted', '  ' . $meta) . PHP_EOL);
            }

            $state = ['objects' => []];
            $dump = _ddDump($value, [
                'maxDepth'  => 5,
                'maxItems'  => 200,
                'maxString' => 4000,
                'indent'    => '    ',
            ], $colors, 0, $state);

            fwrite(STDOUT, _ddIndentLines($dump) . PHP_EOL);

            if ($index < count($values) - 1) {
                fwrite(STDOUT, _ddColor($colors, 'muted', str_repeat('─', $columns)) . PHP_EOL);
            }
        }

        $trace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 2, 8);
        if ($trace !== []) {
            fwrite(STDOUT, _ddColor($colors, 'muted', str_repeat('─', $columns)) . PHP_EOL);
            fwrite(STDOUT, _ddColor($colors, 'cyan', 'Stack trace:') . PHP_EOL);
            foreach ($trace as $i => $frame) {
                $file = isset($frame['file']) ? _ddShortenPath((string) $frame['file']) : '[internal]';
                $lineNo = $frame['line'] ?? '-';
                $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');

                fwrite(
                    STDOUT,
                    '  #' . $i . ' ' .
                    _ddColor($colors, 'green', $file) .
                    _ddColor($colors, 'muted', ':') .
                    _ddColor($colors, 'purple', (string) $lineNo) .
                    _ddColor($colors, 'muted', ' — ') .
                    _ddColor($colors, 'cyan', $func) . PHP_EOL
                );
            }
        }

        fwrite(STDOUT, _ddColor($colors, 'muted', $line) . PHP_EOL);
    }
}

if (!function_exists('_ddRenderHttp')) {
    /**
     * Render dumped values as a Monokai-themed HTML page.
     *
     * @param array<int, mixed> $values
     * @param array<string, mixed> $context
     */
    function _ddRenderHttp(array $values, array $context): void
    {
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            http_response_code(500);
        }

        $trace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 2, 6);

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        echo '<title>Debug Dump</title>';
        echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com">';
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/monokai.min.css">';
        echo '<style>';
        echo 'body{background:#272822;color:#f8f8f2;font-family:ui-monospace,Menlo,Monaco,Consolas,"Courier New",monospace;margin:0;padding:24px;line-height:1.55;}';
        echo '.meta{color:#75715e;font-size:13px;margin:0 0 8px;}';
        echo '.rule{height:1px;background:#3b3c36;margin:18px 0;}';
        echo '.stack{list-style:none;padding:0;margin:0;}';
        echo '.stack li{color:#a6e22e;font-size:13px;margin:4px 0;}';
        echo '.stack span{color:#66d9ef;}';
        echo '.block{background:#1e1f1c;border-radius:10px;padding:18px;margin:18px 0;box-shadow:0 2px 6px rgba(0,0,0,0.4);}';
        echo '.block h2{margin:0;font-size:14px;color:#f92672;font-weight:600;}';
        echo '.block .type{margin:6px 0 12px;font-size:12px;color:#66d9ef;text-transform:uppercase;letter-spacing:0.05em;}';
        echo '.block .meta{color:#75715e;margin:0 0 12px;}';
        echo '.block pre{margin:0;font-size:13px;white-space:pre;overflow:auto;padding:0;background:transparent;}';
        echo '</style></head><body>';

        echo '<div class="meta">Source: ' . htmlspecialchars(_ddShortenPath((string) $context['file']), ENT_QUOTES, 'UTF-8') . ':' . (int) $context['line'] . '</div>';
        echo '<div class="meta">Time: ' . htmlspecialchars((string) $context['time'], ENT_QUOTES, 'UTF-8') .
            ' | PID: ' . (int) $context['pid'] .
            ' | Memory: ' . htmlspecialchars(_ddFormatBytes((int) $context['memory']), ENT_QUOTES, 'UTF-8') .
            ' (peak ' . htmlspecialchars(_ddFormatBytes((int) $context['peak']), ENT_QUOTES, 'UTF-8') . ')</div>';

        if ($trace !== []) {
            echo '<div class="rule"></div><ul class="stack">';
            foreach ($trace as $index => $frame) {
                $file = isset($frame['file']) ? _ddShortenPath((string) $frame['file']) : '[internal]';
                $line = isset($frame['line']) ? (int) $frame['line'] : '-';
                $func = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '[closure]');
                echo '<li>#' . $index . ' ' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . ':' . $line . ' <span>' . htmlspecialchars($func, ENT_QUOTES, 'UTF-8') . '</span></li>';
            }
            echo '</ul><div class="rule"></div>';
        } else {
            echo '<div class="rule"></div>';
        }

        foreach ($values as $index => $value) {
            $state = ['objects' => []];
            $dump = _ddDump($value, [
                'maxDepth'  => 5,
                'maxItems'  => 200,
                'maxString' => 4000,
                'indent'    => '    ',
            ], null, 0, $state);

            echo '<div class="block">';
            echo '<h2>Variable #' . ($index + 1) . '</h2>';
            echo '<div class="type">' . htmlspecialchars(gettype($value), ENT_QUOTES, 'UTF-8') . '</div>';
            $meta = _ddDescribeMeta($value);
            if ($meta !== '') {
                echo '<p class="meta">' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '</p>';
            }
            echo '<pre><code class="language-php">' . htmlspecialchars($dump, ENT_NOQUOTES, 'UTF-8') . '</code></pre>';
            echo '</div>';
        }

        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>';
        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("pre code").forEach(function(block){hljs.highlightElement(block);});});</script>';
        echo '</body></html>';
    }
}

if (!function_exists('_ddDump')) {
    /**
     * Recursively convert a value into a printable string.
     *
     * @param array<string, int|string> $options
     * @param array{objects: array<int, true>} $seen
     */
    function _ddDump(mixed $value, array $options, ?array $colors, int $depth, array &$seen): string
    {
        if ($depth >= (int) $options['maxDepth']) {
            return _ddColor($colors, 'muted', '... (max depth reached)');
        }

        if ($value === null) {
            return _ddColor($colors, 'red', 'null');
        }

        if (is_bool($value)) {
            return _ddColor($colors, 'red', $value ? 'true' : 'false');
        }

        if (is_int($value) || is_float($value)) {
            return _ddColor($colors, 'purple', (string) $value);
        }

        if (is_string($value)) {
            $length = _ddStringLength($value);
            if ($length > (int) $options['maxString']) {
                $value = _ddStringSlice($value, (int) $options['maxString']);
                $value .= '...';
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                $encoded = addslashes($value);
            }

            return _ddColor($colors, 'yellow', (string) $encoded);
        }

        if (is_resource($value) || gettype($value) === 'resource (closed)') {
            $type = get_resource_type($value) ?: 'resource';

            return _ddColor($colors, 'cyan', 'resource(' . $type . ')');
        }

        $indent = str_repeat((string) $options['indent'], $depth);
        $nextIndent = $indent . (string) $options['indent'];

        if (is_array($value)) {
            if ($value === []) {
                return _ddColor($colors, 'muted', '[]');
            }

            $lines = [_ddColor($colors, 'muted', '[')];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count++ >= (int) $options['maxItems']) {
                    $lines[] = $nextIndent . _ddColor($colors, 'muted', '... (items truncated)');
                    break;
                }

                $keyStr = is_int($key)
                    ? _ddColor($colors, 'green', (string) $key)
                    : _ddColor($colors, 'yellow', json_encode((string) $key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: (string) $key);

                $lines[] = $nextIndent . $keyStr . _ddColor($colors, 'muted', ' => ') . _ddDump($item, $options, $colors, $depth + 1, $seen);
            }
            $lines[] = $indent . _ddColor($colors, 'muted', ']');

            return implode(PHP_EOL, $lines);
        }

        if (is_object($value)) {
            $objectId = spl_object_id($value);
            if (isset($seen['objects'][$objectId])) {
                return _ddColor($colors, 'muted', get_class($value) . '#' . $objectId . ' {... recursion}');
            }
            $seen['objects'][$objectId] = true;

            try {
                $reflection = new \ReflectionObject($value);
            } catch (\Throwable $throwable) {
                return _ddColor($colors, 'cyan', get_class($value)) . _ddColor($colors, 'muted', ' {#' . $objectId . ' (reflection error: ' . $throwable->getMessage() . ')}');
            }

            $lines = [
                _ddColor($colors, 'cyan', $reflection->getName()) . _ddColor($colors, 'muted', ' #' . $objectId . ' {')
            ];

            $properties = $reflection->getProperties();
            if ($properties === []) {
                $lines[] = $nextIndent . _ddColor($colors, 'muted', '// no properties');
            } else {
                $count = 0;
                foreach ($properties as $property) {
                    if ($count++ >= (int) $options['maxItems']) {
                        $lines[] = $nextIndent . _ddColor($colors, 'muted', '... (properties truncated)');
                        break;
                    }

                    $property->setAccessible(true);
                    $visibility = $property->isPublic() ? 'public' : ($property->isProtected() ? 'protected' : 'private');
                    $label = _ddColor($colors, 'blue', $visibility) . ' ' . _ddColor($colors, 'yellow', '$' . $property->getName());

                    try {
                        $valueDump = _ddDump($property->getValue($value), $options, $colors, $depth + 1, $seen);
                    } catch (\Throwable $exception) {
                        $valueDump = _ddColor($colors, 'muted', '/* inaccessible: ' . $exception->getMessage() . ' */');
                    }

                    $lines[] = $nextIndent . $label . _ddColor($colors, 'muted', ' = ') . $valueDump;
                }
            }

            $lines[] = $indent . _ddColor($colors, 'muted', '}');

            return implode(PHP_EOL, $lines);
        }

        return _ddColor($colors, 'muted', var_export($value, true));
    }
}

if (!function_exists('_ddCliColumns')) {
    function _ddCliColumns(): int
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
}

if (!function_exists('_ddCliPalette')) {
    function _ddCliPalette(): array
    {
        $reset = "\033[0m";
        $bold = "\033[1m";

        if (_ddSupportsTruecolor()) {
            return [
                'reset' => $reset,
                'bold'  => $bold,
                'fg'    => _ddRgb(248, 248, 242),
                'muted' => _ddRgb(117, 113, 94),
                'red'   => _ddRgb(249, 38, 114),
                'green' => _ddRgb(166, 226, 46),
                'yellow'=> _ddRgb(230, 219, 116),
                'blue'  => _ddRgb(102, 217, 239),
                'purple'=> _ddRgb(174, 129, 255),
                'cyan'  => _ddRgb(102, 217, 239),
            ];
        }

        return [
            'reset' => $reset,
            'bold'  => $bold,
            'fg'    => "\033[37m",
            'muted' => "\033[90m",
            'red'   => "\033[31m",
            'green' => "\033[32m",
            'yellow'=> "\033[33m",
            'blue'  => "\033[34m",
            'purple'=> "\033[35m",
            'cyan'  => "\033[36m",
        ];
    }
}

if (!function_exists('_ddSupportsTruecolor')) {
    function _ddSupportsTruecolor(): bool
    {
        $colorterm = getenv('COLORTERM');
        if (is_string($colorterm) && (stripos($colorterm, 'truecolor') !== false || stripos($colorterm, '24bit') !== false)) {
            return true;
        }

        $term = getenv('TERM');
        if (is_string($term) && stripos($term, 'truecolor') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('_ddRgb')) {
    function _ddRgb(int $r, int $g, int $b): string
    {
        return sprintf("\033[38;2;%d;%d;%dm", $r, $g, $b);
    }
}

if (!function_exists('_ddColor')) {
    function _ddColor(?array $colors, string $key, string $text): string
    {
        if ($colors === null || !isset($colors[$key], $colors['reset'])) {
            return $text;
        }

        return $colors[$key] . $text . $colors['reset'];
    }
}

if (!function_exists('_ddShortenPath')) {
    function _ddShortenPath(string $path): string
    {
        $cwd = getcwd();
        if ($cwd !== false && $path !== '' && str_starts_with($path, $cwd)) {
            $relative = substr($path, strlen($cwd));

            return ltrim((string) $relative, DIRECTORY_SEPARATOR);
        }

        return $path;
    }
}

if (!function_exists('_ddIndentLines')) {
    function _ddIndentLines(string $value, string $indent = '    '): string
    {
        $lines = preg_split("/\r\n|\r|\n/", $value);
        if ($lines === false) {
            return $value;
        }

        return implode(PHP_EOL, array_map(
            static fn (string $line): string => $indent . $line,
            $lines
        ));
    }
}

if (!function_exists('_ddFormatBytes')) {
    function _ddFormatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $position = 0;

        while ($bytes >= 1024 && $position < count($units) - 1) {
            $bytes = (int) round($bytes / 1024);
            $position++;
        }

        return $bytes . ' ' . $units[$position];
    }
}

if (!function_exists('_ddDescribeMeta')) {
    function _ddDescribeMeta(mixed $value): string
    {
        switch (gettype($value)) {
            case 'string':
                return 'length ' . _ddStringLength($value);
            case 'array':
                return 'count ' . count($value);
            case 'object':
                return 'instance of ' . get_class($value);
            case 'resource':
            case 'resource (closed)':
                return 'resource ' . get_resource_type($value);
            default:
                return '';
        }
    }
}

if (!function_exists('_ddStringLength')) {
    function _ddStringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}

if (!function_exists('_ddStringSlice')) {
    function _ddStringSlice(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length, 'UTF-8');
        }

        return substr($value, 0, $length);
    }
}
