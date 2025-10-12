<?php
// Require the autoload file to initialize the application core and dependencies
require_once(core_path('bootstrap/autoload.php'));

// Import composer autoload file if it exists
$composerAutoloadPath = base('vendor/autoload.php');
if(file_exists($composerAutoloadPath)) {
    require_once($composerAutoloadPath);
}

// Import the Router class from the Zero\Lib namespace
use Zero\Lib\Http\Response;
use Zero\Lib\Router;

/**
 * Load environment variables from the .env file or other configuration sources.
 * This function sets up the application's environment.
 */
loadEnvFiles();

bootApplicationHelpers();

/**
 * Include the application routes.
 * This file typically defines all the routes for the web application.
 */
require_once(base('routes/web.php'));

/**
 * Register framework-provided routes (e.g. signed storage endpoints).
 */
require_once(core_path('libraries/Internal/Route.php'));

/**
 * Dispatch the incoming request to the appropriate route handler.
 * - Extract the current URL from the request URI.
 * - Remove any query parameters by splitting the URL at '?'.
 * - Use the Router to match and execute the route handler based on the URL path and HTTP method.
 */
$finalUrl = $_SERVER['REQUEST_URI']; // Get the requested URL from the server
$finalUrl = explode('?', $finalUrl); // Split the URL to isolate the path (ignore query parameters)

$response = Router::dispatch($finalUrl[0], $_SERVER['REQUEST_METHOD']);

if ($response instanceof Response) {
    $response->send();
} elseif (is_string($response)) {
    echo $response;
}
