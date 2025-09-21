<?php
namespace Zero\Lib;

use Exception;

class View
{
    private static array $sections = [];
    private static ?string $currentSection = null;
    private static ?string $layout = null;
    private static array $config = [
        'cache_enabled' => false,
        'cache_path' => '/storage/cache/views',
        'cache_lifetime' => 3600,
        'debug' => false,
    ];

    /**
     * Configure the view system.
     */
    public static function configure(array $config = []): void
    {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * Render a view template and return the resulting HTML.
     *
     * @throws Exception
     */
    public static function render(string $view, array $data = []): string
    {
        self::resetState();

        $viewFile = base("resources/views/{$view}.php");

        if (!file_exists($viewFile)) {
            throw new Exception("View file {$viewFile} not found.");
        }

        $compiledView = self::compileTemplate($view, $viewFile);

        extract($data);

        ob_start();
        eval('?>' . $compiledView);
        $output = ob_get_clean();

        if (self::$layout) {
            $layout = self::$layout;
            $layoutFile = base('resources/views/' . $layout . '.php');

            if (!file_exists($layoutFile)) {
                throw new Exception("Layout file {$layoutFile} not found.");
            }

            $compiledLayout = self::compileTemplate('layout:' . $layout, $layoutFile);

            ob_start();
            eval('?>' . $compiledLayout);
            $output = ob_get_clean();
        }

        self::resetState();

        return $output;
    }

    /**
     * Start a section.
     */
    public static function startSection(string $section): void
    {
        self::$currentSection = $section;
        ob_start();
    }

    /**
     * End the current section.
     */
    public static function endSection(): void
    {
        self::$sections[self::$currentSection] = ob_get_clean();
        self::$currentSection = null;
    }

    /**
     * Yield a section's content.
     */
    public static function yieldSection(string $section): string
    {
        return self::$sections[$section] ?? '';
    }

    /**
     * Define the layout for the view.
     */
    public static function layout(string $layout): void
    {
        self::$layout = $layout;
    }

    /**
     * Include a partial view immediately.
     */
    public static function include(string $view): void
    {
        $viewFile = base("resources/views/{$view}.php");
        if (!file_exists($viewFile)) {
            throw new Exception("View file {$viewFile} not found.");
        }
        include $viewFile;
    }

    /**
     * Clear cached views.
     */
    public static function clearCache(): void
    {
        if (!self::$config['cache_enabled'] || !self::$config['cache_path']) {
            return;
        }

        $cacheDir = rtrim(self::$config['cache_path'], '/') . '/views/cache';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Clear cache for a specific view.
     */
    public static function clearViewCache(string $view): void
    {
        if (!self::$config['cache_enabled'] || !self::$config['cache_path']) {
            return;
        }

        $cacheFile = self::getCacheFilePath($view);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            if (self::$config['debug']) {
                self::log("Cleared cache for view: {$view}");
            }
        }
    }

    /**
     * Configure and persist debugging messages.
     */
    private static function log(string $message): void
    {
        if (!self::$config['debug']) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        $logFile = rtrim(self::$config['cache_path'], '/') . '/views/cache/view.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Compile a view or layout file into executable PHP code.
     */
    private static function compileTemplate(string $identifier, string $path): string
    {
        $useCache = self::$config['cache_enabled'];
        $cacheFile = null;

        if ($useCache) {
            $cacheFile = self::getCacheFilePath($identifier);

            if (!is_dir(dirname($cacheFile))) {
                mkdir(dirname($cacheFile), 0777, true);
            }

            if (self::isCacheValid($cacheFile, $path)) {
                if (self::$config['debug']) {
                    self::log("Using cached version of view: {$identifier}");
                }

                $cached = file_get_contents($cacheFile);

                if ($cached === false) {
                    throw new Exception("Unable to read cached view: {$cacheFile}");
                }

                return $cached;
            }
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new Exception("Unable to read view file: {$path}");
        }

        $compiled = self::processDirectives($raw);

        if ($useCache && $cacheFile !== null) {
            file_put_contents($cacheFile, $compiled);

            if (self::$config['debug']) {
                self::log("Cached new version of view: {$identifier}");
            }
        }

        return $compiled;
    }

    /**
     * Build the cache file path for the given view name.
     */
    private static function getCacheFilePath(string $view): string
    {
        $hash = md5($view);

        return rtrim(self::$config['cache_path'], '/') . "/views/cache/{$hash}.php";
    }

    /**
     * Check whether a cached view is still valid.
     */
    private static function isCacheValid(string $cachePath, string $viewPath): bool
    {
        if (!file_exists($cachePath)) {
            return false;
        }

        if (time() - filemtime($cachePath) > self::$config['cache_lifetime']) {
            return false;
        }

        return filemtime($viewPath) <= filemtime($cachePath);
    }

    /**
     * Process Laravel-style directives into native PHP code.
     */
    private static function processDirectives(string $content): string
    {
        $escapedTriplePlaceholder = '__ESCAPED_TRIPLE_BRACE_OPEN__';
        $escapedDoublePlaceholder = '__ESCAPED_DOUBLE_BRACE_OPEN__';

        $content = str_replace('@{{{', $escapedTriplePlaceholder, $content);
        $content = str_replace('@{{', $escapedDoublePlaceholder, $content);

        $content = preg_replace_callback('/@foreach\s*\((.*?)\)\s*/', fn($matches) => "<?php foreach({$matches[1]}): ?>", $content);
        $content = str_replace('@endforeach', '<?php endforeach; ?>', $content);

        $content = preg_replace_callback('/@if\s*\((.*?)\)\s*/', fn($matches) => "<?php if({$matches[1]}): ?>", $content);
        $content = str_replace('@endif', '<?php endif; ?>', $content);

        $content = preg_replace_callback('/@elseif\s*\((.*?)\)\s*/', fn($matches) => "<?php elseif({$matches[1]}): ?>", $content);
        $content = str_replace('@else', '<?php else: ?>', $content);

        $content = preg_replace_callback('/{{{(.*?)}}}/', fn($matches) => "<?php echo {$matches[1]}; ?>", $content);
        $content = preg_replace_callback('/{{\s*(.+?)\s*}}/', fn($matches) => "<?php echo htmlspecialchars({$matches[1]}, ENT_QUOTES, 'UTF-8'); ?>", $content);

        $content = preg_replace_callback('/@include\s*\((.*?)\)\s*/', function ($matches) {
            $includePath = eval('return ' . $matches[1] . ';');
            $includePath = base("/views/" . $includePath);
            if (file_exists($includePath)) {
                ob_start();
                include $includePath;
                return ob_get_clean();
            }
            throw new Exception("Included file not found: $includePath");
        }, $content);

        $content = preg_replace_callback('/@yield\s*\((.*?)\)\s*/', fn($matches) => "<?php echo View::yieldSection({$matches[1]}); ?>", $content);
        $content = preg_replace_callback('/@layout\s*\((.*?)\)\s*/', fn($matches) => "<?php View::layout({$matches[1]}); ?>", $content);
        $content = preg_replace_callback('/@section\s*\((.*?)\)\s*/', fn($matches) => "<?php View::startSection({$matches[1]}); ?>", $content);
        $content = str_replace('@endsection', '<?php View::endSection(); ?>', $content);

        $content = preg_replace_callback('/@dd\s*\((.*?)\)\s*/', fn($matches) => "<?php dd({$matches[1]}); ?>", $content);

        $content = str_replace($escapedTriplePlaceholder, '{{{', $content);
        $content = str_replace($escapedDoublePlaceholder, '{{', $content);

        return $content;
    }

    /**
     * Reset static state between renders to prevent cross-contamination.
     */
    private static function resetState(): void
    {
        self::$sections = [];
        self::$currentSection = null;
        self::$layout = null;
    }
}
