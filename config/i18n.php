<?php

return [
    'web' => [
        // Default locale for HTTP/web usage.
        'default_locale' => 'en',

        // Optional list of supported locales. Leave empty to allow any locale.
        'supported_locales' => [
            'en',
            'id',
            'it',
            'cn',
        ],

        // Locale fallback chain.
        'fallback_locales' => [
            'en',
        ],

        // Locale resolver strategy: url | subdomain | domain | session | cookie | header | custom
        'resolver' => 'subdomain',

        // URL prefix strategy (e.g. /en/{page}).
        'url' => [
            'enabled' => true,
            'segment' => 1,
        ],

        // Session + cookie keys used for locale storage.
        'session_key' => 'locale',
        'cookie_key' => 'locale',

        // Custom header to resolve the locale from.
        'header' => 'X-Locale',

        // Domain strategy (exact host mapping + optional subdomain fallback).
        'domain' => [
            // Exact host -> locale map.
            'map' => [
                // 'id.zero.local' => 'id',
                // 'nl.zero.local' => 'nl',
            ],
        ],

        // Optional callable (closure or "Class::method") for custom resolution.
        'custom_resolver' => null,

        // Base path for translation files.
        'path' => 'resources/i18n',
    ],  

    'email' => [
        'default_locale' => 'en',
        'supported_locales' => [
            'en',
            'id',
            'it',
            'cn',
        ],
        'fallback_locales' => [
            'en',
        ],
        'resolver' => 'custom',
        'custom_resolver' => null,
        'domain' => [
            'map' => [
                // 'id.zero.local' => 'id',
                // 'nl.zero.local' => 'nl',
            ],
        ],
        'path' => 'resources/i18n',
        'view_prefix' => 'mail',
        'context' => 'mail',
    ],
];
