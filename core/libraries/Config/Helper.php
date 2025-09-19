<?php

if(!function_exists('config')) {
    /**
     * Global configuration storage array
     * Caches loaded configuration files to prevent multiple file reads
     * 
     * @var array
     */
    $configs = [];

    /**
     * Retrieves configuration values using dot notation
     * 
     * Loads and caches configuration files from the config directory.
     * Supports nested configuration access using dot notation.
     * Example: config('app.name') - loads config/app.php and returns the 'name' value
     * 
     * @param string $key Dot notation key (e.g., 'app.debug', 'database.mysql.host')
     * @return mixed The configuration value or null if not found
     * 
     * Usage examples:
     * - config('app.name') -> Returns value of $config['name'] from config/app.php
     * - config('database.connections.mysql.host') -> Returns MySQL host from database config
     */
    function config($key) {
        // Access the global config cache
        global $configs;

        // Split the key into file name and nested keys
        $key = explode('.', $key);
        $file = $key[0];
        array_shift($key);

        // Load config file if not cached, otherwise use cached version
        $config = isset($configs[$file]) 
            ? $configs[$file] 
            : require_once(base('/config/' . $file . '.php'));

        // Store in cache if not already cached
        if(!isset($configs[$file])) {
            $configs[$file] = $config;
        }

        // Traverse the nested configuration array
        foreach($key as $k) {
            $config = $config[$k];
        }

        return $config;
    }
}