<?php

if (! function_exists('config')) {
    $GLOBALS['__zero_configs'] = [];

    function config($key, $default = null) {
        if ($key === null || $key === '') {
            return $default;
        }

        $parts = explode('.', (string) $key);
        $file = $parts[0];
        array_shift($parts);

        if (! isset($GLOBALS['__zero_configs'][$file])) {
            $filePath = base('/config/' . $file . '.php');
            if (! file_exists($filePath)) {
                return $default;
            }
            $GLOBALS['__zero_configs'][$file] = require $filePath;
        }

        $config = $GLOBALS['__zero_configs'][$file];

        foreach ($parts as $k) {
            if (is_array($config) && array_key_exists($k, $config)) {
                $config = $config[$k];
            } else {
                return $default;
            }
        }

        return $config;
    }
}
