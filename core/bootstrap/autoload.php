<?php

// Include Composer's autoloader if present so third-party packages are available.
$composerAutoload = base('vendor/autoload.php');
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Initialize the kernel if it isn't set already
$kernel = isset($kernel) ? $kernel : require_once(core_path('kernel.php'));
// Get the aliases from the kernel configuration
$aliases = $kernel['aliases'];

/**
 * Function to get the path of a library file.
 *
 * @param string $file The name of the library file
 * @return string The full path to the library file
 */
function getLibPath(string $class): ?string {
    $prefixes = [
        'Zero\\Lib\\' => 'libraries/',
        'Zero\\DB\\' => 'libraries/DB/',
        'Database\\' => 'database/',
    ];

    foreach ($prefixes as $prefix => $base) {
        if (strpos($class, $prefix) !== 0) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $normalized = str_replace('\\', '/', $relative);

        if ($prefix === 'Database\\') {
            $parts = explode('/', $normalized);
            if (!empty($parts)) {
                $parts[0] = strtolower($parts[0]);
                $normalized = implode('/', $parts);
            }
        }

        $candidates = [
            core_path($base . $normalized . '.php'),
        ];

        if (strpos($relative, '\\') === false) {
            $candidates[] = core_path($base . $relative . '/' . $relative . '.php');
        }

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
    }

    return null;
}

/**
 * Autoloader function that loads classes dynamically.
 */
spl_autoload_register(function ($className) use ($aliases) {
    
    // Store the original class name for alias handling
    $originalClassName = $className;
    // Flag to check if the alias is being used
    $canAliases        = false;


    // If the class has an alias, update the class name to the alias
    if(array_key_exists($className, $aliases)) {
        $canAliases = true;
        $className = $aliases[$className];
    }

    if ($classPath = getLibPath($className)) {
        require_once $classPath;

        if($canAliases && !class_exists($originalClassName)) {
            class_alias($aliases[$originalClassName], $originalClassName);
        }

        return;
    }

    // Handle classes in the App\Controllers namespace
    if(strpos($className, 'App\Controllers') !== false) {
        $className = str_replace('App\Controllers\\', '', $className);
        $relative = str_replace('\\', '/', $className);
        $class_path = app_path('controllers/' . $relative . '.php');
        // If the controller file exists, require it
        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

    // Handle classes in the App\Models namespace
    if(strpos($className, 'App\Models') !== false) {
        $className = str_replace('App\Models\\', '', $className);
        $relative = str_replace('\\', '/', $className);
        $class_path = app_path('models/' . $relative . '.php');

        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

    // Handle classes in the App\Middlewares namespace
    if(strpos($className, 'App\Middlewares') !== false) {
        $className = str_replace('App\Middlewares\\', '', $className);
        $class_path = app_path('middlewares/' . str_replace('\\', '/', $className) . '.php');

        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

    // Handle classes in the Database\Seeders namespace
    if (str_starts_with($className, 'Database\\Seeders')) {
        $className = str_replace('Database\\Seeders\\', '', $className);
        $relative = str_replace('\\', '/', $className);
        $class_path = base('database/seeders/' . $relative . '.php');

        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

    // Handle classes in the App\Services namespace
    if(strpos($className, 'App\Services') !== false) {
        $className = str_replace('App\Services\\', '', $className);
        $class_path = app_path('services/' . str_replace('\\', '/', $className) . '.php');

        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

    // Handle classes in the App\Logging namespace
    if(strpos($className, 'App\Logging') !== false) {
        $className = str_replace('App\Logging\\', '', $className);
        $class_path = app_path('logging/' . str_replace('\\', '/', $className) . '.php');

        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

    // Handle classes in the App\Console\Commands namespace
    if(strpos($className, 'App\Console\Commands') !== false) {
        $className = str_replace('App\Console\Commands\\', '', $className);
        $class_path = app_path('console/Commands/' . str_replace('\\', '/', $className) . '.php');

        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

    // Handle classes in the App\Console namespace
    if(strpos($className, 'App\Console') === 0) {
        $relative = str_replace('App\Console\\', '', $className);
        $class_path = app_path('console/' . str_replace('\\', '/', $relative) . '.php');

        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

    // Handle classes in the App\\Helpers namespace
    if(strpos($className, 'App\\Helpers') !== false) {
        $className = str_replace('App\\Helpers\\', '', $className);
        $class_path = app_path('helpers/' . str_replace('\\', '/', $className) . '.php');

        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

    // Handle classes in the Drivers namespace
    if(strpos($className, 'Drivers') !== false) {
        // Remove the namespace prefix and the 'Driver' suffix
        $className = str_replace('Zero\Drivers\\', '', $className);
        $className = str_replace('Drivers\\', '', $className);
        $fileName = str_replace('Driver', '', $className);
        
        // Get the full path of the driver file
        $class_path = core_path("drivers/$fileName.php");
        // If the driver file exists, require it
        if (file_exists($class_path)) {
            require_once $class_path;
            return;
        }
    }

});
