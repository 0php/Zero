<?php

declare(strict_types=1);

namespace Zero\Lib\I18n;

use Zero\Lib\Http\Request;
use Zero\Lib\Session;
use Zero\Lib\View\ViewCompiler;

final class Translator
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $catalog = [];

    /** @var array<string, array<string, array<string, bool>>> */
    private static array $loadedFiles = [];

    /** @var array<string, string> */
    private static array $resolvedLocales = [];

    /** @var array<int, string> */
    private static array $contextStack = ['web'];

    public static function pushContext(string $context): void
    {
        $context = $context !== '' ? $context : 'web';
        self::$contextStack[] = $context;
    }

    public static function popContext(): void
    {
        if (count(self::$contextStack) > 1) {
            array_pop(self::$contextStack);
        }
    }

    public static function currentContext(): string
    {
        return self::$contextStack[count(self::$contextStack) - 1] ?? 'web';
    }

    public static function resolveContextForView(string $viewPath): string
    {
        $config = self::config();
        $emailConfig = is_array($config['email'] ?? null) ? $config['email'] : [];
        $prefix = trim((string) ($emailConfig['view_prefix'] ?? 'mail'), '/');
        $context = (string) ($emailConfig['context'] ?? 'mail');

        if ($prefix !== '' && ($viewPath === $prefix || str_starts_with($viewPath, $prefix . '/'))) {
            return $context !== '' ? $context : 'mail';
        }

        return 'web';
    }

    public static function setLocale(string $locale, ?string $context = null): void
    {
        $context = $context ?? self::currentContext();
        $locale = self::normalizeLocale($locale);

        if ($locale !== '') {
            self::$resolvedLocales[$context] = $locale;
        }
    }

    /**
     * Set locale using configured rules (supported locales + default), optionally persisting.
     */
    public static function setLocaleFromConfig(string $locale, ?string $context = null, bool $persist = true): string
    {
        $context = $context ?? self::currentContext();
        $config = self::resolveContextConfig($context);
        $supported = self::supportedLocales($config);
        $normalized = self::normalizeLocale($locale);

        if ($normalized === '' || !self::isSupported($normalized, $supported)) {
            $normalized = self::normalizeLocale((string) ($config['default_locale'] ?? 'en'));
            if ($normalized === '' && $supported !== []) {
                $normalized = $supported[0];
            }
        }

        if ($normalized === '') {
            return '';
        }

        self::setLocale($normalized, $context);

        if ($persist) {
            self::persistLocale($normalized, $config);
        }

        return $normalized;
    }

    /**
     * Return configured locales (supported list or default fallback).
     *
     * @return string[]
     */
    public static function availableLocales(?string $context = null): array
    {
        $context = $context ?? self::currentContext();
        $config = self::resolveContextConfig($context);
        $supported = self::supportedLocales($config);

        if ($supported !== []) {
            return $supported;
        }

        $defaultLocale = self::normalizeLocale((string) ($config['default_locale'] ?? 'en'));

        return $defaultLocale !== '' ? [$defaultLocale] : [];
    }

    public static function locale(?string $context = null): string
    {
        $context = $context ?? self::currentContext();

        if (!isset(self::$resolvedLocales[$context])) {
            self::$resolvedLocales[$context] = self::resolveLocale($context);
        }

        return self::$resolvedLocales[$context];
    }

    public static function translate(
        string $key,
        array $replacements = [],
        ?string $locale = null,
        ?string $context = null
    ): string {
        $context = $context ?? self::currentContext();
        $locale = $locale ?? self::locale($context);

        foreach (self::localeChain($locale, $context) as $candidate) {
            $value = self::getTranslation($key, $candidate, $context);

            if ($value !== null) {
                return self::renderTemplate((string) $value, $replacements);
            }
        }

        return $key;
    }

    public static function useView(string $viewPath, ?string $context = null): void
    {
        $context = $context ?? self::currentContext();
        self::useFile($viewPath, null, $context);
    }

    public static function useFile(string $file, ?string $locale = null, ?string $context = null): void
    {
        $context = $context ?? self::currentContext();
        $locale = $locale ?? self::locale($context);

        foreach (self::localeChain($locale, $context) as $candidate) {
            self::loadFile($file, $candidate, $context);
        }
    }

    public static function registerInline(string $format, string $content, ?string $context = null): void
    {
        $context = $context ?? self::currentContext();
        $format = strtolower(trim($format));

        if ($format === 'yaml' || $format === 'yml') {
            $data = self::parseYaml($content);

            if ($data === []) {
                $data = self::parseJson($content);
            }
        } else {
            $data = match ($format) {
                'json' => self::parseJson($content),
                default => [],
            };
        }

        if (!is_array($data) || $data === []) {
            return;
        }

        if (self::looksLikeLocaleMap($data)) {
            foreach ($data as $locale => $translations) {
                if (!is_array($translations)) {
                    continue;
                }

                $locale = self::normalizeLocale((string) $locale);
                if ($locale === '') {
                    continue;
                }

                self::mergeTranslations($context, $locale, $translations);
            }

            return;
        }

        $locale = self::locale($context);
        self::mergeTranslations($context, $locale, $data);
    }

    private static function config(): array
    {
        try {
            $config = config('i18n');
        } catch (\Throwable) {
            $config = [];
        }

        return is_array($config) ? $config : [];
    }

    private static function resolveContextConfig(string $context): array
    {
        $config = self::config();

        $webConfig = $config['web'] ?? [];
        if (!is_array($webConfig)) {
            $webConfig = [];
        }

        if ($context === 'mail' || $context === 'email') {
            $emailConfig = $config['email'] ?? [];

            return is_array($emailConfig) ? $emailConfig : [];
        }

        return $webConfig;
    }

    private static function resolveLocale(string $context): string
    {
        $config = self::resolveContextConfig($context);
        $defaultLocale = self::normalizeLocale((string) ($config['default_locale'] ?? 'en'));
        $supported = self::supportedLocales($config);

        $source = $config['resolver'] ?? $config['resolver_order'] ?? null;

        if (is_array($source)) {
            $source = $source[0] ?? null;
        }

        $source = is_string($source) ? $source : 'url';

        $locale = self::resolveFromSource($source, $config);
        $locale = self::normalizeLocale($locale);

        if ($locale !== '' && self::isSupported($locale, $supported)) {
            return $locale;
        }

        if ($defaultLocale === '' && $supported !== []) {
            return $supported[0];
        }

        return $defaultLocale !== '' ? $defaultLocale : 'en';
    }

    private static function resolveFromSource(string $source, array $config): string
    {
        return match ($source) {
            'url' => self::resolveFromUrl($config),
            'subdomain' => self::resolveFromSubdomain($config),
            'domain' => self::resolveFromDomain($config),
            'session' => self::resolveFromSession($config),
            'cookie' => self::resolveFromCookie($config),
            'header' => self::resolveFromHeader($config),
            'custom' => self::resolveFromCustom($config),
            default => '',
        };
    }

    private static function resolveFromUrl(array $config): string
    {
        $urlConfig = $config['url'] ?? [];
        $enabled = is_array($urlConfig) ? ($urlConfig['enabled'] ?? false) : (bool) $urlConfig;

        if (!$enabled || !class_exists(Request::class)) {
            return '';
        }

        $segmentIndex = 1;
        if (is_array($urlConfig) && isset($urlConfig['segment'])) {
            $segmentIndex = max(1, (int) $urlConfig['segment']);
        }

        $path = Request::instance()->path();
        if ($path === '/' || $path === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        $index = $segmentIndex - 1;

        return $segments[$index] ?? '';
    }

    private static function resolveFromSession(array $config): string
    {
        if (!class_exists(Request::class)) {
            return '';
        }

        $key = (string) ($config['session_key'] ?? 'locale');

        return (string) Request::instance()->session($key, '');
    }

    private static function resolveFromCookie(array $config): string
    {
        if (!class_exists(Request::class)) {
            return '';
        }

        $key = (string) ($config['cookie_key'] ?? 'locale');

        return (string) Request::instance()->cookie($key, '');
    }

    private static function resolveFromHeader(array $config): string
    {
        if (!class_exists(Request::class)) {
            return '';
        }

        $header = (string) ($config['header'] ?? 'X-Locale');
        $value = (string) Request::instance()->header($header, '');

        if ($value === '' && strcasecmp($header, 'accept-language') === 0) {
            $value = (string) Request::instance()->header('accept-language', '');
        }

        if ($value === '') {
            return '';
        }

        if (str_contains($value, ',')) {
            $value = explode(',', $value, 2)[0] ?? $value;
        }

        if (str_contains($value, ';')) {
            $value = explode(';', $value, 2)[0] ?? $value;
        }

        return trim($value);
    }

    private static function resolveFromSubdomain(array $config): string
    {
        return self::resolveFromHostConfig($config, true);
    }

    private static function resolveFromDomain(array $config): string
    {
        return self::resolveFromHostConfig($config, false);
    }

    private static function resolveFromHostConfig(array $config, bool $subdomainOnly): string
    {
        if (!class_exists(Request::class)) {
            return '';
        }

        $host = self::resolveRequestHost();
        if ($host === '') {
            return '';
        }

        $domainConfig = $config['domain'] ?? $config['domains'] ?? [];
        if (!is_array($domainConfig)) {
            $domainConfig = [];
        }

        if (!$subdomainOnly) {
            $map = self::resolveDomainMap($domainConfig);

            foreach ($map as $domain => $locale) {
                if (!is_string($domain) || !is_string($locale)) {
                    continue;
                }

                $normalizedDomain = self::normalizeHost($domain);
                if ($normalizedDomain === '') {
                    continue;
                }

                if ($normalizedDomain === $host) {
                    return $locale;
                }
            }
        }

        $subConfig = $domainConfig['subdomain'] ?? $config['subdomain'] ?? [];
        if (!is_array($subConfig)) {
            $subConfig = [];
        }

        if (array_key_exists('enabled', $subConfig) && !(bool) $subConfig['enabled']) {
            return '';
        }

        $offset = 0;
        $ignore = ['www'];

        if (isset($subConfig['offset'])) {
            $offset = max(0, (int) $subConfig['offset']);
        }

        if (isset($subConfig['ignore']) && is_array($subConfig['ignore'])) {
            $ignore = array_values($subConfig['ignore']);
        }

        $segments = array_values(array_filter(explode('.', $host), 'strlen'));
        if (count($segments) < 3) {
            return '';
        }

        $subdomain = $segments[$offset] ?? '';
        if ($subdomain === '') {
            return '';
        }

        if (in_array(strtolower($subdomain), array_map('strtolower', $ignore), true)) {
            return '';
        }

        return $subdomain;
    }

    private static function resolveDomainMap(array $domainConfig): array
    {
        if (isset($domainConfig['map']) && is_array($domainConfig['map'])) {
            return $domainConfig['map'];
        }

        $map = [];

        foreach ($domainConfig as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $map[$key] = $value;
        }

        return $map;
    }

    private static function persistLocale(string $locale, array $config): void
    {
        $source = $config['resolver'] ?? $config['resolver_order'] ?? null;
        $sources = is_array($source) ? $source : [$source];

        foreach ($sources as $strategy) {
            if (!is_string($strategy)) {
                continue;
            }

            $strategy = strtolower($strategy);

            if ($strategy === 'session') {
                if (class_exists(Session::class)) {
                    $key = (string) ($config['session_key'] ?? 'locale');
                    Session::set($key, $locale);
                }
                continue;
            }

            if ($strategy === 'cookie') {
                self::queueLocaleCookie($locale, $config);
            }
        }
    }

    private static function queueLocaleCookie(string $locale, array $config): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $key = (string) ($config['cookie_key'] ?? 'locale');
        $sessionConfig = [];

        try {
            $sessionConfig = config('session');
        } catch (\Throwable) {
            $sessionConfig = [];
        }

        $lifetimeMinutes = (int) ($sessionConfig['lifetime'] ?? 120);
        $expiry = time() + max(60, $lifetimeMinutes * 60);
        $path = (string) ($sessionConfig['path'] ?? '/');
        $domain = $sessionConfig['domain'] ?? null;
        $secure = (bool) ($sessionConfig['secure'] ?? false);
        $httpOnly = (bool) ($sessionConfig['http_only'] ?? true);
        $sameSite = strtolower((string) ($sessionConfig['same_site'] ?? 'lax'));

        $options = [
            'expires' => $expiry,
            'path' => $path,
            'secure' => $secure,
            'httponly' => $httpOnly,
        ];

        if (is_string($domain) && $domain !== '') {
            $options['domain'] = $domain;
        }

        if (in_array($sameSite, ['lax', 'strict', 'none'], true)) {
            $options['samesite'] = ucfirst($sameSite);
        }

        setcookie($key, $locale, $options);
        $_COOKIE[$key] = $locale;
    }

    private static function resolveRequestHost(): string
    {
        $host = '';

        if (class_exists(Request::class)) {
            $host = (string) Request::instance()->header('host', '');
        }

        if ($host === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
        }

        return self::normalizeHost($host);
    }

    private static function normalizeHost(string $host): string
    {
        $host = trim(strtolower($host));
        if ($host === '') {
            return '';
        }

        if (str_contains($host, '://')) {
            $parsed = parse_url($host);
            if (is_array($parsed) && isset($parsed['host']) && is_string($parsed['host'])) {
                $host = $parsed['host'];
            }
        }

        $parsed = parse_url('//' . $host);
        if (is_array($parsed) && isset($parsed['host']) && is_string($parsed['host'])) {
            $host = $parsed['host'];
        }

        return trim(strtolower($host));
    }

    private static function resolveFromCustom(array $config): string
    {
        $resolver = $config['custom_resolver'] ?? null;

        if (is_string($resolver) && $resolver !== '' && is_callable($resolver)) {
            return (string) $resolver();
        }

        if (is_callable($resolver)) {
            try {
                return (string) $resolver();
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }

    private static function supportedLocales(array $config): array
    {
        $supported = $config['supported_locales'] ?? [];

        if (!is_array($supported)) {
            return [];
        }

        $supported = array_values(array_filter(array_map([self::class, 'normalizeLocale'], $supported)));

        return array_values(array_unique($supported));
    }

    private static function fallbackLocales(array $config): array
    {
        $fallbacks = $config['fallback_locales'] ?? $config['fallbacks'] ?? [];

        if (!is_array($fallbacks)) {
            return [];
        }

        $fallbacks = array_values(array_filter(array_map([self::class, 'normalizeLocale'], $fallbacks)));

        return array_values(array_unique($fallbacks));
    }

    private static function localeChain(string $locale, string $context): array
    {
        $config = self::resolveContextConfig($context);
        $fallbacks = self::fallbackLocales($config);

        $chain = array_filter(array_merge([$locale], $fallbacks));

        $unique = [];
        foreach ($chain as $candidate) {
            $candidate = self::normalizeLocale($candidate);
            if ($candidate !== '' && !in_array($candidate, $unique, true)) {
                $unique[] = $candidate;
            }
        }

        return $unique;
    }

    private static function isSupported(string $locale, array $supported): bool
    {
        if ($supported === []) {
            return true;
        }

        return in_array(self::normalizeLocale($locale), $supported, true);
    }

    private static function loadFile(string $file, string $locale, string $context): void
    {
        $file = trim($file, '/');
        if ($file === '') {
            return;
        }

        $locale = self::normalizeLocale($locale);
        if ($locale === '') {
            return;
        }

        if (isset(self::$loadedFiles[$context][$locale][$file])) {
            return;
        }

        $config = self::resolveContextConfig($context);
        $basePath = trim((string) ($config['path'] ?? 'resources/i18n'), '/');

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $baseFile = $ext ? substr($file, 0, -strlen($ext) - 1) : $file;

        $extensions = $ext ? [$ext] : ['json', 'yaml', 'yml'];

        $data = [];

        foreach ($extensions as $extension) {
            $path = base($basePath . '/' . $locale . '/' . $baseFile . '.' . $extension);

            if (!file_exists($path)) {
                continue;
            }

            $raw = file_get_contents($path);
            if ($raw === false) {
                continue;
            }

            $parsed = match ($extension) {
                'json' => self::parseJson($raw),
                'yaml', 'yml' => self::parseYaml($raw),
                default => [],
            };

            if (is_array($parsed) && $parsed !== []) {
                $data = array_replace_recursive($data, $parsed);
            }
        }

        if ($data !== []) {
            self::mergeTranslations($context, $locale, $data);
        }

        self::$loadedFiles[$context][$locale][$file] = true;
    }

    private static function mergeTranslations(string $context, string $locale, array $translations): void
    {
        if (!isset(self::$catalog[$context][$locale])) {
            self::$catalog[$context][$locale] = [];
        }

        self::$catalog[$context][$locale] = array_replace_recursive(
            self::$catalog[$context][$locale],
            $translations
        );
    }

    private static function getTranslation(string $key, string $locale, string $context): mixed
    {
        $catalog = self::$catalog[$context][$locale] ?? null;

        if (!is_array($catalog)) {
            return null;
        }

        $value = self::arrayGet($catalog, $key);

        if (is_array($value)) {
            return null;
        }

        return $value;
    }

    private static function arrayGet(array $data, string $key): mixed
    {
        if ($key === '') {
            return $data;
        }

        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if (is_array($data) && array_key_exists($segment, $data)) {
                $data = $data[$segment];
            } else {
                return null;
            }
        }

        return $data;
    }

    private static function renderTemplate(string $template, array $data): string
    {
        if ($data === [] || $template === '') {
            return $template;
        }

        $compiled = ViewCompiler::compile($template, [
            'enable_foreach' => false,
            'enable_for' => false,
            'enable_if' => false,
            'enable_includes' => false,
            'enable_layouts' => false,
            'enable_sections' => false,
            'enable_yield' => false,
            'enable_dd' => false,
            'enable_php' => false,
        ]);

        extract($data, EXTR_SKIP);

        ob_start();
        eval('?>' . $compiled);
        $output = ob_get_clean();

        return $output === false ? $template : $output;
    }

    private static function parseJson(string $content): array
    {
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function parseYaml(string $content): array
    {
        if (\function_exists('yaml_parse')) {
            $parsed = \call_user_func('yaml_parse', $content);

            return is_array($parsed) ? $parsed : [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $root = [];
        $stack = [
            [
                'indent' => -1,
                'ref' => &$root,
            ],
        ];

        foreach ($lines as $line) {
            if ($line === '' || trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            $trimmed = trim($line);
            if ($trimmed === '---' || $trimmed === '...') {
                continue;
            }

            $indent = strspn($line, ' ');
            $line = ltrim($line, ' ');

            if (!str_contains($line, ':')) {
                continue;
            }

            [$rawKey, $rawValue] = explode(':', $line, 2);
            $key = trim($rawKey);
            if ($key === '') {
                continue;
            }

            while (count($stack) > 1 && $indent <= $stack[count($stack) - 1]['indent']) {
                array_pop($stack);
            }

            $parent = &$stack[count($stack) - 1]['ref'];
            $value = ltrim($rawValue, " \t");

            if ($value === '') {
                $parent[$key] = [];
                $stack[] = [
                    'indent' => $indent,
                    'ref' => &$parent[$key],
                ];
                continue;
            }

            $parent[$key] = self::parseYamlScalar($value);
        }

        return $root;
    }

    private static function parseYamlScalar(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        if (str_contains($value, ' #')) {
            $value = explode(' #', $value, 2)[0];
        }

        return $value;
    }

    private static function looksLikeLocaleMap(array $data): bool
    {
        if ($data === []) {
            return false;
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                return false;
            }
        }

        return true;
    }

    private static function normalizeLocale(string $locale): string
    {
        $locale = trim($locale);
        if ($locale === '') {
            return '';
        }

        $locale = str_replace('_', '-', $locale);
        $parts = explode('-', $locale);

        $parts[0] = strtolower($parts[0]);
        if (isset($parts[1])) {
            $parts[1] = strtoupper($parts[1]);
        }

        return implode('-', $parts);
    }
}
