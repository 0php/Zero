<?php

/**
 * Load and parse environment files in order of priority
 * 
 * Loads configuration from multiple .env files in the following order:
 * - .env (lowest priority)
 * - .env.staging
 * - .env.production (highest priority)
 * 
 * Supports:
 * - Single values: KEY=value
 * - Array values: ARRAY=[item1,item2,item3]
 * - Variable interpolation: URL=${HOST}:${PORT}
 * 
 * @return array Parsed environment configuration data
 */
function loadEnvFiles(): array
{
    // Define env files in order of increasing priority
    $files = ['.env', '.env.staging', '.env.production'];
    $envData = [];

    // Load files and merge based on priority 
    foreach ($files as $file) {
        $filePath = base($file);
        if (file_exists($filePath)) {
            // Read file content, skipping empty lines and line endings
            $content = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($content as $line) {
                // Skip comments and lines without assignment
                if (strpos(trim($line), '#') === 0 || !str_contains($line, '=')) {
                    continue;
                }

                // Split line into key and value
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Parse array values enclosed in square brackets [item1,item2]
                if (preg_match('/^\[(.*)]$/', $value, $matches)) {
                    $items = array_map('trim', explode(',', $matches[1]));
                    // Process variables in array items
                    $items = array_map(function($item) use ($envData) {
                        return interpolateEnvVariables($item, $envData);
                    }, $items);
                    $envData[$key] = $items;
                } else {
                    // Process variables in single values
                    $envData[$key] = interpolateEnvVariables($value, $envData);
                }
            }
        }
    }

    // Store final config in global $_ENV array
    $_ENV['CONFIG'] = $envData;

    return $envData;
}

/**
 * Helper function to interpolate environment variables in a string
 * 
 * Supports ${VAR} syntax for variable interpolation
 * 
 * @param string $value The string containing variables to interpolate
 * @param array $envData The current environment data
 * @return string The interpolated string
 */
function interpolateEnvVariables(string $value, array $envData): string
{
    return preg_replace_callback('/\${([^}]+)}/', function($matches) use ($envData) {
        $varName = $matches[1];
        return $envData[$varName] ?? ''; // Return empty string if variable not found
    }, $value);
}

/**
 * Helper function to retrieve environment variables
 * 
 * Only defined if not already exists to prevent conflicts
 * 
 * @param string $key The configuration key to look up
 * @param mixed $default Default value if key not found
 * @return mixed The configuration value or default if not found
 */
if(!function_exists('env')) {
    function env(string $key, $default = null) {
        return $_ENV['CONFIG'][$key] ?? $default;
    }
}

/**
 * Helper function to get base path for files
 * 
 * @param string $path The relative path to append to base path
 * @return string The complete file path
 */
if(!function_exists('base')) {
    function base(string $path = ''): string {
        static $base;
        if (!$base) {
            $base = dirname(__DIR__);
        }
        return $base . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}