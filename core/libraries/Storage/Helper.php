<?php


/**
 * Get the path to the base folder
 */
if(!function_exists('base')) {
    function base($path = ''){
        $root = rtrim($_ENV['BASE_PATH'], '/\\');
        $normalized = ltrim((string) $path, '/\\');

        return $normalized === '' ? $root : $root . '/' . $normalized;
    }
}

/**
 * Get the path to the app folder
 */

if(!function_exists('view_path')) {
    function viewpath($path = '') {
        return base('/resources/views/' . $path);
    }
}

/**
 * Get the path to the public folder
 */

if(!function_exists('public_path')) {
    function public_path($path = '') {
        return base('/public/' . $path);
    }
}

/**
 * Get the path to the storage folder
 */
if(!function_exists('storage_path')) {
    function storage_path($path = '') {
        return base('/storage/' . $path);
    }
}

/**
 * Get the path to the log folder
 */
if(!function_exists('log_path')) {
    function log_path($path = '') {
        return storage_path('logs/' . $path);
    }
}


/**
 * Get the path to the config folder
 */

if(!function_exists('config_path')) {
    function config_path($path = '') {
        return base('/config/' . $path);
    }
}

/**
 * Get the path for cache folder
 */

if(!function_exists('cache_path')) {
    function cache_path($path = '') {
        return storage_path('cache/' . $path);
    }
}


/**
 * Core path
 */

if(!function_exists('core_path')) {
    function core_path($path = '') {
        return base('/core/' . $path);
    }
}


/**
 * Lib path
 */

if(!function_exists('lib_path')) {
    function lib_path($path = '') {
        return base('/core/libraries/' . $path);
    }
}


/**
 * App Path
 */

if(!function_exists('app_path')) {
    function app_path($path = '') {
        return base('/app/' . $path);
    }
}
