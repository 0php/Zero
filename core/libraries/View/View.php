<?php
namespace Zero\Lib;

use Exception;
use Zero\Lib\I18n\Translator;
use Zero\Lib\View\ViewCompiler;

class View
{
    private static array $sections = [];
    private static ?string $currentSection = null;
    private static ?string $layout = null;
    private static array $layoutData = [];
    private static array $shared = [];
    private static array $directives = [];
    private static array $composers = [];
    private static array $config = [];

    /**
     * Configure the view system.
     */
    public static function configure(array $config = []): void
    {
        self::$config = array_merge(self::getConfig(), $config);
    }

    /**
     * Return resolved config, reading env variables on first call.
     * Supported .env keys: VIEW_CACHE (true/false), VIEW_CACHE_PATH,
     * VIEW_CACHE_LIFETIME (seconds), VIEW_DEBUG (true/false).
     */
    private static function getConfig(): array
    {
        if (self::$config === []) {
            self::$config = [
                'cache_enabled'  => filter_var(env('VIEW_CACHE', false), FILTER_VALIDATE_BOOLEAN),
                'cache_path'     => env('VIEW_CACHE_PATH', base('storage/framework')),
                'cache_lifetime' => (int) env('VIEW_CACHE_LIFETIME', 86400),
                'debug'          => filter_var(env('VIEW_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
            ];
        }
        return self::$config;
    }

    /**
     * Render a view template and return the resulting HTML.
     *
     * @throws Exception
     */
    public static function render(string $view, array $data = []): string
    {
        self::resetState();

        $viewPath = self::normalizeViewName($view);
        $viewFile = base("resources/views/{$viewPath}.php");

        if (!file_exists($viewFile)) {
            throw new Exception("View file {$viewFile} not found.");
        }

        $context = Translator::resolveContextForView($viewPath);
        Translator::pushContext($context);
        Translator::useView($viewPath, $context);

        try {
            $compiledView = self::compileTemplate($viewPath, $viewFile);

            self::runComposers($viewPath);

            extract($data, EXTR_SKIP);

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

                if (self::$layoutData !== []) {
                    extract(self::$layoutData, EXTR_OVERWRITE);
                }

                ob_start();
                eval('?>' . $compiledLayout);
                $output = ob_get_clean();
            }

            return $output;
        } finally {
            self::resetState();
            Translator::popContext();
        }
    }

    /**
     * Render a template string and return the resulting HTML.
     *
     * @throws Exception
     */
    public static function renderString(string $template, array $data = []): string
    {
        self::resetState();

        $compiledView = self::compileTemplateString($template);

        if ($data !== []) {
            extract($data, EXTR_SKIP);
        }

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

            if (self::$layoutData !== []) {
                extract(self::$layoutData, EXTR_OVERWRITE);
            }

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
    public static function layout(string $layout, array $data = []): void
    {
        self::$layout = self::normalizeViewName($layout);
        self::$layoutData = $data;
    }

    /**
     * Share a value with every template in the current render. The view
     * executes before the layout, so a page can `share()` at the top and the
     * layout/head will see the value when it renders.
     *
     * Cleared between renders by resetState().
     */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * Read a shared value (or default).
     */
    public static function shared(string $key, mixed $default = null): mixed
    {
        return self::$shared[$key] ?? $default;
    }

    /**
     * Append a value to a shared array bucket. Useful when several pieces of
     * code want to contribute to the same hook (e.g. extra <link> tags,
     * preload hints, body classes).
     */
    public static function push(string $key, mixed $value): void
    {
        if (!isset(self::$shared[$key]) || !is_array(self::$shared[$key])) {
            self::$shared[$key] = [];
        }
        self::$shared[$key][] = $value;
    }

    /**
     * Register a custom Blade-style directive. The callback receives the raw
     * PHP-like argument string (everything between the parentheses) and must
     * return the compiled PHP snippet to inline. Example:
     *
     *   View::directive('jsonld', fn($args) => "<?php View::share('jsonld', {$args}); ?>");
     *
     * The compiler picks these up automatically.
     */
    public static function directive(string $name, callable $compile): void
    {
        self::$directives[$name] = $compile;
    }

    /**
     * @return array<string, callable>
     */
    public static function directives(): array
    {
        return self::$directives;
    }

    /**
     * Register a view composer — a callback fired right before a view (or
     * group of views) is rendered. Useful for injecting shared state without
     * touching every page. Pass `*` to match all views.
     *
     *   View::composer('pages.home.*', fn() => View::share('og_image', '...'));
     */
    public static function composer(string $pattern, callable $callback): void
    {
        self::$composers[] = ['pattern' => $pattern, 'callback' => $callback];
    }

    /**
     * Internal: run any composers matching the given view path.
     */
    public static function runComposers(string $viewPath): void
    {
        foreach (self::$composers as $composer) {
            if (self::matchPattern($composer['pattern'], $viewPath)) {
                ($composer['callback'])($viewPath);
            }
        }
    }

    private static function matchPattern(string $pattern, string $viewPath): bool
    {
        if ($pattern === '*' || $pattern === $viewPath) {
            return true;
        }
        $regex = '#^' . str_replace(['\*', '\.\*'], ['.*', '.*'], preg_quote($pattern, '#')) . '$#';
        return (bool) preg_match($regex, $viewPath);
    }

    /**
     * Include a partial view immediately.
     */
    public static function include(string $view, array $data = []): void
    {
        $viewPath = self::normalizeViewName($view);
        $viewFile = base("resources/views/{$viewPath}.php");
        if (!file_exists($viewFile)) {
            throw new Exception("View file {$viewFile} not found.");
        }

        Translator::useView($viewPath);

        if ($data !== []) {
            extract($data, EXTR_SKIP);
        }

        $compiled = self::compileTemplate('include:' . $viewPath, $viewFile);

        eval('?>' . $compiled);
    }

    /**
     * Clear cached views.
     */
    public static function clearCache(): void
    {
        if (!self::getConfig()['cache_enabled'] || !self::getConfig()['cache_path']) {
            return;
        }

        $cacheDir = rtrim(self::getConfig()['cache_path'], '/') . '/views/cache';
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
        if (!self::getConfig()['cache_enabled'] || !self::getConfig()['cache_path']) {
            return;
        }

        $cacheFile = self::getCacheFilePath(self::normalizeViewName($view));
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            if (self::getConfig()['debug']) {
                self::log("Cleared cache for view: {$view}");
            }
        }
    }

    /**
     * Configure and persist debugging messages.
     */
    private static function log(string $message): void
    {
        if (!self::getConfig()['debug']) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        $logFile = rtrim(self::getConfig()['cache_path'], '/') . '/views/cache/view.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Compile a view or layout file into executable PHP code.
     */
    private static function compileTemplate(string $identifier, string $path): string
    {
        $useCache = self::getConfig()['cache_enabled'];
        $cacheFile = null;

        if ($useCache) {
            $cacheFile = self::getCacheFilePath($identifier);

            if (!is_dir(dirname($cacheFile))) {
                mkdir(dirname($cacheFile), 0777, true);
            }

            if (self::isCacheValid($cacheFile, $path)) {
                if (self::getConfig()['debug']) {
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

            if (self::getConfig()['debug']) {
                self::log("Cached new version of view: {$identifier}");
            }
        }

        return $compiled;
    }

    /**
     * Compile a raw template string into executable PHP code.
     */
    private static function compileTemplateString(string $content): string
    {
        $useCache = self::getConfig()['cache_enabled'];
        $cacheFile = null;
        $identifier = 'string:' . md5($content);

        if ($useCache) {
            $cacheFile = self::getCacheFilePath($identifier);

            if (!is_dir(dirname($cacheFile))) {
                mkdir(dirname($cacheFile), 0777, true);
            }

            if (self::isStringCacheValid($cacheFile)) {
                if (self::getConfig()['debug']) {
                    self::log("Using cached version of view: {$identifier}");
                }

                $cached = file_get_contents($cacheFile);

                if ($cached === false) {
                    throw new Exception("Unable to read cached view: {$cacheFile}");
                }

                return $cached;
            }
        }

        $compiled = self::processDirectives($content);

        if ($useCache && $cacheFile !== null) {
            file_put_contents($cacheFile, $compiled);

            if (self::getConfig()['debug']) {
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

        return rtrim(self::getConfig()['cache_path'], '/') . "/views/cache/{$hash}.php";
    }

    /**
     * Check whether a cached view is still valid.
     */
    private static function isCacheValid(string $cachePath, string $viewPath): bool
    {
        if (!file_exists($cachePath)) {
            return false;
        }

        $lifetime = self::getConfig()['cache_lifetime'];
        if ($lifetime > 0 && time() - filemtime($cachePath) > $lifetime) {
            return false;
        }

        return filemtime($viewPath) <= filemtime($cachePath);
    }

    /**
     * Check whether a cached string template is still valid.
     */
    private static function isStringCacheValid(string $cachePath): bool
    {
        if (!file_exists($cachePath)) {
            return false;
        }

        $lifetime = self::getConfig()['cache_lifetime'];
        return $lifetime === 0 || time() - filemtime($cachePath) <= $lifetime;
    }

    /**
     * Process Laravel-style directives into native PHP code.
     */
    private static function processDirectives(string $content): string
    {
        return ViewCompiler::compile($content);
    }

    private static function normalizeViewName(string $view): string
    {
        $normalized = str_replace('.', '/', trim($view));

        return trim($normalized, '/');
    }

    /**
     * Reset static state between renders to prevent cross-contamination.
     */
    private static function resetState(): void
    {
        self::$sections = [];
        self::$currentSection = null;
        self::$layout = null;
        self::$layoutData = [];
        self::$shared = [];
        // NOTE: directives & composers persist across renders by design.
    }
}
