<?php

namespace Zero\Lib;

use Exception;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Log;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

class Router
{
    private static array $routes = [];
    private static array $middlewares = [];
    private static string $prefix = '';
    private static array $groupMiddlewares = [];

    /**
     * Create a route group with shared attributes.
     */
    public static function group(array $attributes, callable $callback): void
    {
        $previousPrefix = self::$prefix;
        $previousMiddlewares = self::$groupMiddlewares;

        self::$prefix .= $attributes['prefix'] ?? '';
        if (isset($attributes['middleware'])) {
            $middlewares = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
            self::$groupMiddlewares = array_merge(self::$groupMiddlewares, $middlewares);
        }

        $callback();

        self::$prefix = $previousPrefix;
        self::$groupMiddlewares = $previousMiddlewares;
    }

    /**
     * Register a route for a specific HTTP verb.
     */
    private static function addRoute(string $method, string $route, array $action, array $middlewares = []): void
    {
        $fullRoute = self::$prefix . '/' . trim($route, '/');
        $fullRoute = '/' . trim($fullRoute, '/');

        $allMiddlewares = array_merge(self::$groupMiddlewares, $middlewares);

        self::$routes[strtoupper($method)][$fullRoute] = $action;
        self::$middlewares[strtoupper($method)][$fullRoute] = $allMiddlewares;
    }

    public static function get(string $route, array $action, array $middlewares = []): void
    {
        self::addRoute('GET', $route, $action, $middlewares);
    }

    public static function post(string $route, array $action, array $middlewares = []): void
    {
        self::addRoute('POST', $route, $action, $middlewares);
    }

    public static function put(string $route, array $action, array $middlewares = []): void
    {
        self::addRoute('PUT', $route, $action, $middlewares);
    }

    public static function patch(string $route, array $action, array $middlewares = []): void
    {
        self::addRoute('PATCH', $route, $action, $middlewares);
    }

    public static function delete(string $route, array $action, array $middlewares = []): void
    {
        self::addRoute('DELETE', $route, $action, $middlewares);
    }

    /**
     * Dispatch the current request and return a response instance.
     */
    public static function dispatch(string $requestUri, string $requestMethod): Response
    {
        $request = Request::capture();
        $requestUri = trim($requestUri, '/');
        $routes = self::$routes[strtoupper($requestMethod)] ?? [];

        foreach ($routes as $route => $action) {
            try {
                $pattern = self::compileRouteToRegex($route);

                if (preg_match($pattern, $requestUri, $matches)) {
                    $parameters = self::extractRouteParameters($matches);

                    $middlewareResponse = self::validateMiddlewares($route, strtoupper($requestMethod));
                    if ($middlewareResponse instanceof Response) {
                        return $middlewareResponse;
                    }

                    $result = self::callAction($action, $parameters);

                    return Response::resolve($result);
                }
            } catch (Throwable $e) {
                $debug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);

                Log::error('Error processing route', [
                    'route' => $route,
                    'method' => $requestMethod,
                    'message' => $e->getMessage(),
                ]);

                if ($debug) {
                    throw $e;
                }

                if (function_exists('zero_build_error_response')) {
                    return zero_build_error_response(500, [
                        'title' => 'Server Error',
                        'message' => 'An unexpected issue occurred while processing the request.',
                    ]);
                }

                return Response::json([
                    'message' => 'An unexpected issue occurred while processing the request.',
                ], 500);
            }
        }

        if (function_exists('zero_build_error_response')) {
            return zero_build_error_response(404);
        }

        return Response::make('404 Not Found', 404);
    }

    /**
     * Validate and execute middlewares; allow early responses.
     */
    private static function validateMiddlewares(string $route, string $requestMethod): ?Response
    {
        $middlewares = self::$middlewares[$requestMethod][$route] ?? [];

        foreach ($middlewares as $middleware) {
            if (!class_exists($middleware)) {
                throw new Exception("Middleware {$middleware} not found");
            }

            $middlewareInstance = new $middleware();

            if (!method_exists($middlewareInstance, 'handle')) {
                throw new Exception("Method handle not found in middleware {$middleware}");
            }

            $result = self::invokeMiddleware($middlewareInstance);

            if ($result !== null) {
                return Response::resolve($result);
            }
        }

        return null;
    }

    /**
     * Invoke the target controller/method pair with resolved parameters.
     */
    private static function callAction(array $action, array $parameters): mixed
    {
        [$controller, $method] = $action;

        if (!class_exists($controller)) {
            throw new RuntimeException("Controller {$controller} not found");
        }

        $controllerInstance = new $controller();

        if (!method_exists($controllerInstance, $method)) {
            throw new RuntimeException("Method {$method} not found in controller {$controller}");
        }

        $arguments = self::resolveMethodDependencies($controllerInstance, $method, $parameters);

        return $controllerInstance->{$method}(...$arguments);
    }

    /**
     * Convert the registered route into a regular expression pattern.
     */
    private static function compileRouteToRegex(string $route): string
    {
        $routePattern = trim($route, '/');
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $routePattern);

        return '#^' . $pattern . '(?:/)?$#';
    }

    /**
     * Extract parameter values from a regex match array.
     */
    private static function extractRouteParameters(array $matches): array
    {
        $named = [];

        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $named[$key] = $value;
            }
        }

        if (!empty($named)) {
            return array_values($named);
        }

        unset($matches[0]);

        return array_values($matches);
    }

    /**
     * Resolve controller method dependencies and map route parameters.
     */
    private static function resolveMethodDependencies(object $controller, string $method, array $routeParameters): array
    {
        $reflection = new ReflectionMethod($controller, $method);
        $resolved = [];
        $routeValues = array_values($routeParameters);
        $index = 0;

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();

                if (is_a($className, Request::class, true) || is_a(Request::class, $className, true)) {
                    $resolved[] = Request::instance();
                    continue;
                }

                throw new RuntimeException(sprintf(
                    'Unable to resolve dependency [%s] for %s::%s()',
                    $className,
                    $reflection->getDeclaringClass()->getName(),
                    $method
                ));
            }

            if ($index < count($routeValues)) {
                $value = $routeValues[$index++];

                if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                    $value = self::castRouteParameter($value, $type->getName());
                }

                $resolved[] = $value;
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException(sprintf(
                'Missing required route parameter [%s] for %s::%s()',
                $parameter->getName(),
                $reflection->getDeclaringClass()->getName(),
                $method
            ));
        }

        while ($index < count($routeValues)) {
            $resolved[] = $routeValues[$index++];
        }

        return $resolved;
    }

    /**
     * Cast route parameters to the expected scalar type.
     */
    private static function castRouteParameter(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            default => (string) $value,
        };
    }

    /**
     * Invoke middleware handle methods with optional dependency resolution.
     */
    private static function invokeMiddleware(object $middleware): mixed
    {
        $method = new ReflectionMethod($middleware, 'handle');
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_a($type->getName(), Request::class, true)) {
                $arguments[] = Request::instance();
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException(sprintf(
                'Unable to resolve middleware dependency [%s] on %s::handle()',
                $parameter->getName(),
                $middleware::class
            ));
        }

        return $method->invokeArgs($middleware, $arguments);
    }
}
