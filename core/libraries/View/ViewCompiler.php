<?php

declare(strict_types=1);

namespace Zero\Lib\View;

final class ViewCompiler
{
    private const TRIPLE_PLACEHOLDER = '__ESCAPED_TRIPLE_BRACE_OPEN__';
    private const DOUBLE_PLACEHOLDER = '__ESCAPED_DOUBLE_BRACE_OPEN__';

    /**
     * Compile blade-like directives within the given content to executable PHP.
     *
     * @param array{
     *     escape_echo?: bool,
     *     expression_resolver?: callable(string): string,
     *     enable_foreach?: bool,
     *     enable_for?: bool,
     *     enable_if?: bool,
     *     enable_includes?: bool,
     *     enable_layouts?: bool,
     *     enable_sections?: bool,
     *     enable_yield?: bool,
     *     enable_dd?: bool,
     *     enable_php?: bool,
     *     enable_triple_curly?: bool,
     *     enable_raw_curly?: bool,
     *     enable_escaped_curly?: bool,
     * } $options
     */
    public static function compile(string $content, array $options = []): string
    {
        $options = array_merge(self::defaults(), $options);

        $resolver = self::expressionResolver($options);

        $content = str_replace('@{{{', self::TRIPLE_PLACEHOLDER, $content);
        $content = str_replace('@{{', self::DOUBLE_PLACEHOLDER, $content);

        if ($options['enable_foreach']) {
            $content = self::compileForeachDirectives($content);
        }

        if ($options['enable_for']) {
            $content = self::replaceDirectiveWithArguments(
                $content,
                'for',
                fn(string $arguments) => "<?php for ({$arguments}): ?>"
            );
            $content = str_replace('@endfor', '<?php endfor; ?>', $content);
        }

        if ($options['enable_if']) {
            $content = self::replaceDirectiveWithArguments(
                $content,
                'if',
                fn(string $arguments) => "<?php if ({$arguments}): ?>"
            );
            $content = str_replace('@endif', '<?php endif; ?>', $content);

            $content = self::replaceDirectiveWithArguments(
                $content,
                'elseif',
                fn(string $arguments) => "<?php elseif ({$arguments}): ?>"
            );
            $content = str_replace('@else', '<?php else: ?>', $content);
        }

        if ($options['enable_triple_curly']) {
            $content = preg_replace_callback(
                '/{{{(.*?)}}}/s',
                fn(array $matches) => self::compileRawEcho($resolver($matches[1])),
                $content
            );
        }

        if ($options['enable_raw_curly']) {
            $content = preg_replace_callback(
                '/{!!\s*(.+?)\s*!!}/s',
                fn(array $matches) => self::compileRawEcho($resolver($matches[1])),
                $content
            );
        }

        if ($options['enable_escaped_curly']) {
            $escapeEcho = static fn(string $expression): string => $options['escape_echo']
                ? "<?php echo htmlspecialchars(({$expression}) ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                : "<?php echo ({$expression}) ?? ''; ?>";

            $content = preg_replace_callback(
                '/{{\s*(.+?)\s*}}/',
                fn(array $matches) => $escapeEcho($resolver($matches[1])),
                $content
            );
        }

        if ($options['enable_includes']) {
            $content = self::replaceDirectiveWithArguments(
                $content,
                'include',
                fn(string $arguments) => "<?php View::include({$arguments}); ?>"
            );
        }

        if ($options['enable_yield']) {
            $content = self::replaceDirectiveWithArguments(
                $content,
                'yield',
                fn(string $arguments) => "<?php echo View::yieldSection({$arguments}); ?>"
            );
        }

        if ($options['enable_layouts']) {
            $content = self::replaceDirectiveWithArguments(
                $content,
                'layout',
                fn(string $arguments) => "<?php View::layout({$arguments}); ?>"
            );
        }

        if ($options['enable_sections']) {
            $content = self::replaceDirectiveWithArguments(
                $content,
                'section',
                fn(string $arguments) => "<?php View::startSection({$arguments}); ?>"
            );
            $content = str_replace('@endsection', '<?php View::endSection(); ?>', $content);
        }

        if ($options['enable_dd']) {
            $content = self::replaceDirectiveWithArguments(
                $content,
                'dd',
                fn(string $arguments) => "<?php dd({$arguments}); ?>"
            );
        }

        if ($options['enable_php']) {
            $content = self::replaceDirectiveWithArguments(
                $content,
                'php',
                function (string $arguments): string {
                    $arguments = rtrim($arguments);

                    if ($arguments === '' || substr($arguments, -1) !== ';') {
                        $arguments .= ';';
                    }

                    return "<?php {$arguments} ?>";
                }
            );

            $content = preg_replace('/(?<!\\w)@php(?!\s*\()/m', '<?php', $content);
            $content = preg_replace('/(?<!\\w)@endphp(?!\\w)/m', '?>', $content);
        }

        $content = str_replace(self::TRIPLE_PLACEHOLDER, '{{{', $content);
        $content = str_replace(self::DOUBLE_PLACEHOLDER, '{{', $content);

        return $content;
    }

    private static function defaults(): array
    {
        return [
            'escape_echo' => true,
            'expression_resolver' => fn(string $expression): string => trim($expression),
            'enable_foreach' => true,
            'enable_for' => true,
            'enable_if' => true,
            'enable_includes' => true,
            'enable_layouts' => true,
            'enable_sections' => true,
            'enable_yield' => true,
            'enable_dd' => true,
            'enable_php' => true,
            'enable_triple_curly' => true,
            'enable_raw_curly' => true,
            'enable_escaped_curly' => true,
        ];
    }

    private static function expressionResolver(array $options): callable
    {
        $resolver = $options['expression_resolver'];

        return static fn(string $expression): string => trim($resolver(trim($expression)));
    }

    private static function compileRawEcho(string $expression): string
    {
        return "<?php echo {$expression}; ?>";
    }

    private static function replaceDirectiveWithArguments(
        string $content,
        string $directive,
        callable $handler
    ): string {
        $pattern = '/@' . preg_quote($directive, '/') . '\s*(\((?:[^()]+|(?1))*\))/m';

        return preg_replace_callback($pattern, function (array $matches) use ($handler) {
            $arguments = substr($matches[1], 1, -1);

            return $handler(trim($arguments));
        }, $content);
    }

    private static function compileForeachDirectives(string $content): string
    {
        $pattern = '/@foreach|@empty|@endforeach/';
        $offset = 0;
        $result = '';
        $stack = [];
        $counter = 0;
        $length = strlen($content);

        while (preg_match($pattern, $content, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $directive = $match[0][0];
            $position = $match[0][1];

            $result .= substr($content, $offset, $position - $offset);

            if ($directive === '@foreach') {
                $counter++;
                $afterDirective = $position + strlen($directive);
                [$arguments, $nextOffset] = self::extractDirectiveArguments($content, $afterDirective);

                if ($arguments === null) {
                    $result .= '<?php foreach';
                    $result .= substr($content, $afterDirective, $nextOffset - $afterDirective);
                    $result .= ': ?>';
                    $stack[] = ['supportsEmpty' => false];
                    $offset = $nextOffset;
                    continue;
                }

                [$source, $asPart] = $arguments;

                if ($source === null || $asPart === null) {
                    $result .= "<?php foreach ({$arguments[2]}): ?>";
                    $stack[] = ['supportsEmpty' => false];
                    $offset = $nextOffset;
                    continue;
                }

                $dataVar = "__foreach_{$counter}_data";
                $emptyVar = "__foreach_{$counter}_empty";

                $result .= '<?php $' . $dataVar . ' = ' . $source . '; $' . $emptyVar
                    . ' = true; foreach (is_iterable($' . $dataVar . ') ? $' . $dataVar
                    . ' : [] as ' . $asPart . '): $' . $emptyVar . ' = false; ?>';

                $stack[] = [
                    'supportsEmpty' => true,
                    'emptyVar' => $emptyVar,
                    'hasEmpty' => false,
                ];

                $offset = $nextOffset;
                continue;
            }

            if ($directive === '@empty') {
                if ($stack === []) {
                    $result .= '@empty';
                    $offset = $position + strlen($directive);
                    continue;
                }

                $index = count($stack) - 1;
                if ($stack[$index]['supportsEmpty'] === false) {
                    $result .= '@empty';
                    $offset = $position + strlen($directive);
                    continue;
                }

                if ($stack[$index]['hasEmpty'] === true) {
                    $result .= '@empty';
                    $offset = $position + strlen($directive);
                    continue;
                }

                $emptyVar = $stack[$index]['emptyVar'];
                $result .= '<?php endforeach; if ($' . $emptyVar . '): ?>';
                $stack[$index]['hasEmpty'] = true;

                $offset = $position + strlen($directive);
                continue;
            }

            if ($stack === []) {
                $result .= '@endforeach';
                $offset = $position + strlen($directive);
                continue;
            }

            $current = array_pop($stack);

            if ($current['supportsEmpty'] && $current['hasEmpty']) {
                $result .= '<?php endif; ?>';
            } else {
                $result .= '<?php endforeach; ?>';
            }

            $offset = $position + strlen($directive);
        }

        if ($offset < $length) {
            $result .= substr($content, $offset);
        }

        return $result;
    }

    private static function extractDirectiveArguments(string $content, int $offset): array
    {
        $length = strlen($content);

        while ($offset < $length && ctype_space($content[$offset])) {
            $offset++;
        }

        if ($offset >= $length || $content[$offset] !== '(') {
            return [null, $offset];
        }

        $offset++;
        $start = $offset;
        $depth = 1;
        $inString = false;
        $quoteChar = '';

        while ($offset < $length) {
            $char = $content[$offset];

            if ($inString) {
                if ($char === $quoteChar && !self::isEscapedCharacter($content, $offset)) {
                    $inString = false;
                }
                $offset++;
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $inString = true;
                $quoteChar = $char;
                $offset++;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $offset++;
                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $arguments = substr($content, $start, $offset - $start);
                    $split = self::splitForeachArguments($arguments);

                    return [$split, $offset + 1];
                }
                $offset++;
                continue;
            }

            $offset++;
        }

        return [null, $length];
    }

    private static function splitForeachArguments(string $arguments): array
    {
        $length = strlen($arguments);
        $depth = 0;
        $inString = false;
        $quoteChar = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $arguments[$i];

            if ($inString) {
                if ($char === $quoteChar && !self::isEscapedCharacter($arguments, $i)) {
                    $inString = false;
                }
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $inString = true;
                $quoteChar = $char;
                continue;
            }

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
                continue;
            }

            if ($char === ')' || $char === ']' || $char === '}') {
                if ($depth > 0) {
                    $depth--;
                }
                continue;
            }

            if (($char === 'a' || $char === 'A') && $depth === 0) {
                $nextIndex = $i + 1;
                if ($nextIndex < $length && ($arguments[$nextIndex] === 's' || $arguments[$nextIndex] === 'S')) {
                    $before = $i === 0 ? '' : $arguments[$i - 1];
                    $after = $nextIndex + 1 < $length ? $arguments[$nextIndex + 1] : '';

                    if (!self::isIdentifierChar($before) && !self::isIdentifierChar($after)) {
                        $source = trim(substr($arguments, 0, $i));
                        $asPart = trim(substr($arguments, $nextIndex + 1));

                        return [$source, $asPart, $arguments];
                    }
                }
            }
        }

        return [null, null, $arguments];
    }

    private static function isIdentifierChar(string $char): bool
    {
        return $char !== '' && (ctype_alnum($char) || $char === '_');
    }

    private static function isEscapedCharacter(string $content, int $position): bool
    {
        $backslashes = 0;
        $index = $position - 1;

        while ($index >= 0 && $content[$index] === '\\') {
            $backslashes++;
            $index--;
        }

        return $backslashes % 2 === 1;
    }
}
