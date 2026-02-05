<?php

if (!function_exists('__')) {
    /**
     * Translate the given key using the active locale.
     *
     * @param array<string, mixed> $replacements
     */
    function __(string $key, array $replacements = [], ?string $context = null, ?string $locale = null): string
    {
        return \Zero\Lib\I18n\Translator::translate($key, $replacements, $locale, $context);
    }
}

if (!function_exists('locale')) {
    /**
     * Get the active locale for the current context.
     */
    function locale(?string $context = null): string
    {
        return \Zero\Lib\I18n\Translator::locale($context);
    }
}

if (!function_exists('locales')) {
    /**
     * Get the configured locale list for the given context.
     *
     * @return string[]
     */
    function locales(?string $context = null): array
    {
        return \Zero\Lib\I18n\Translator::availableLocales($context);
    }
}

if (!function_exists('set_locale')) {
    /**
     * Change the active locale based on configuration (supported locales + default).
     */
    function set_locale(string $locale, ?string $context = null, bool $persist = true): string
    {
        return \Zero\Lib\I18n\Translator::setLocaleFromConfig($locale, $context, $persist);
    }
}
