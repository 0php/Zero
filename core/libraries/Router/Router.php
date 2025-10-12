<?php

namespace Zero\Lib;

use Exception;
use InvalidArgumentException;
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
    private static string $namePrefix = '';
    private static array $namedRoutes = [];

    /**
     * Create a route group with shared attributes.
     */
    public static function group(array $attributes, callable $callback): void
    {
        $previousPrefix = self::$prefix;
        $previousMiddlewares = self::$groupMiddlewares;
        $previousNamePrefix = self::$namePrefix;

        self::$prefix .= $attributes['prefix'] ?? '';
        if (isset($attributes['middleware'])) {
            $middlewares = self::normalizeMiddlewareList($attributes['middleware']);
            self::$groupMiddlewares = array_merge(self::$groupMiddlewares, $middlewares);
        }

        if (isset($attributes['name'])) {
            $nameSegment = trim((string) $attributes['name']);
            if ($nameSegment !== '') {
                $normalized = rtrim($nameSegment, '.') . '.';
                self::$namePrefix .= $normalized;
            }
        }

        $callback();

        self::$prefix = $previousPrefix;
        self::$groupMiddlewares = $previousMiddlewares;
        self::$namePrefix = $previousNamePrefix;
    }

    /**
     * Register a route for a specific HTTP verb.
     */
    private static function addRoute(string $method, string $route, array $action, mixed $middlewares = null): RouteDefinition
    {
        $method = strtoupper($method);
        $fullRoute = self::$prefix . '/' . trim($route, '/');
        $fullRoute = '/' . trim($fullRoute, '/');
        if ($fullRoute === '//') {
            $fullRoute = '/';
        }

        $normalizedMiddlewares = self::normalizeMiddlewareList($middlewares);
        $allMiddlewares = array_merge(self::$groupMiddlewares, $normalizedMiddlewares);

        self::$routes[$method][$fullRoute] = [
            'action' => $action,
            'name' => null,
        ];
        self::$middlewares[$method][$fullRoute] = $allMiddlewares;

        return new RouteDefinition($method, $fullRoute, self::$namePrefix);
    }

    public static function get(string $route, array $action, mixed $middlewares = null): RouteDefinition
    {
        return self::addRoute('GET', $route, $action, $middlewares);
    }

    public static function post(string $route, array $action, mixed $middlewares = null): RouteDefinition
    {
        return self::addRoute('POST', $route, $action, $middlewares);
    }

    public static function put(string $route, array $action, mixed $middlewares = null): RouteDefinition
    {
        return self::addRoute('PUT', $route, $action, $middlewares);
    }

    public static function patch(string $route, array $action, mixed $middlewares = null): RouteDefinition
    {
        return self::addRoute('PATCH', $route, $action, $middlewares);
    }

    public static function delete(string $route, array $action, mixed $middlewares = null): RouteDefinition
    {
        return self::addRoute('DELETE', $route, $action, $middlewares);
    }

    /**
     * Dispatch the current request and return a response instance.
     */
    public static function dispatch(string $requestUri, string $requestMethod): Response
    {
        $request = Request::capture();
        $requestUri = trim($requestUri, '/');
        $method = strtoupper($requestMethod);
        $routes = self::$routes[$method] ?? [];

        foreach ($routes as $route => $definition) {
            try {
        $pattern = self::compileRouteToRegex($route);

                if (preg_match($pattern, $requestUri, $matches)) {
                    $parameters = self::extractRouteParameters($matches);

                    $middlewareResponse = self::validateMiddlewares($route, $method);
                    if ($middlewareResponse instanceof Response) {
                        return $middlewareResponse;
                    }

                    $result = self::callAction($definition['action'], $parameters);

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

        foreach ($middlewares as $definition) {
            [$middleware, $parameters] = self::normalizeMiddleware($definition);

            if (!class_exists($middleware)) {
                throw new Exception("Middleware {$middleware} not found");
            }

            $middlewareInstance = new $middleware();

            if (!method_exists($middlewareInstance, 'handle')) {
                throw new Exception("Method handle not found in middleware {$middleware}");
            }

            $result = self::invokeMiddleware($middlewareInstance, $parameters);

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
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)(:[^}]+)?\}/', function (array $matches): string {
            $name = $matches[1];
            $custom = $matches[2] ?? null;
            $regex = $custom !== null ? substr($custom, 1) : '[^/]+';

            return '(?P<' . $name . '>' . $regex . ')';
        }, $routePattern);

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
    private static function invokeMiddleware(object $middleware, array $parameters = []): mixed
    {
        $method = new ReflectionMethod($middleware, 'handle');
        $arguments = [];
        $extraIndex = 0;

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_a($type->getName(), Request::class, true)) {
                $arguments[] = Request::instance();
                continue;
            }

            if ($extraIndex < count($parameters)) {
                $arguments[] = $parameters[$extraIndex++];
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

    /**
     * Normalize middleware declarations into a consistent list of definitions.
     */
    private static function normalizeMiddlewareList(mixed $middlewares): array
    {
        if ($middlewares === null || $middlewares === []) {
            return [];
        }

        if (is_string($middlewares)) {
            return [$middlewares];
        }

        if (!is_array($middlewares)) {
            throw new InvalidArgumentException('Invalid middleware configuration.');
        }

        if (self::isMiddlewareDefinitionArray($middlewares)) {
            return [$middlewares];
        }

        $normalized = [];

        foreach ($middlewares as $entry) {
            if ($entry === null || $entry === []) {
                continue;
            }

            if (is_string($entry)) {
                $normalized[] = $entry;
                continue;
            }

            if (is_array($entry) && self::isMiddlewareDefinitionArray($entry)) {
                $normalized[] = $entry;
                continue;
            }

            throw new InvalidArgumentException('Invalid middleware definition encountered.');
        }

        return $normalized;
    }

    private static function isMiddlewareDefinitionArray(array $value): bool
    {
        if ($value === [] || !self::isListArray($value)) {
            return false;
        }

        $class = $value[0] ?? null;

        if (!self::looksLikeClassString($class)) {
            return false;
        }

        foreach (array_slice($value, 1) as $item) {
            if (is_array($item) && self::isMiddlewareDefinitionArray($item)) {
                return false;
            }

            if (self::looksLikeClassString($item)) {
                return false;
            }
        }

        return true;
    }

    private static function isListArray(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    private static function looksLikeClassString(mixed $value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        if (str_contains($value, '\\')) {
            return true;
        }

        return class_exists($value);
    }

    public static function getRoutes(): array
    {
        $routes = [];

        foreach (self::$routes as $method => $definitions) {
            foreach ($definitions as $path => $definition) {
                $action = $definition['action'];
                $actionString = self::formatAction($action);
                $name = $definition['name'] ?? null;
                $middleware = self::$middlewares[$method][$path] ?? [];

                $routes[] = [
                    'method' => $method,
                    'uri' => $path,
                    'name' => $name,
                    'action' => $actionString,
                    'middleware' => array_map(
                        static fn ($entry) => self::stringifyMiddleware($entry),
                        $middleware
                    ),
                ];
            }
        }

        usort($routes, static function (array $a, array $b): int {
            return [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']];
        });

        return $routes;
    }

    private static function formatAction(mixed $action): string
    {
        if (is_array($action) && count($action) === 2 && is_string($action[0]) && is_string($action[1])) {
            return $action[0] . '@' . $action[1];
        }

        if (is_string($action)) {
            return $action;
        }

        if ($action instanceof \Closure) {
            return 'Closure';
        }

        return 'Callable';
    }

    private static function stringifyMiddleware(mixed $entry): string
    {
        if (is_string($entry)) {
            return $entry;
        }

        if (is_array($entry) && isset($entry[0])) {
            $class = (string) $entry[0];
            $parameters = array_map('strval', array_slice($entry, 1));

            if ($parameters === []) {
                return $class;
            }

            return $class . ':' . implode(',', $parameters);
        }

        return (string) $entry;
    }

    public static function appendRouteMiddleware(string $method, string $path, mixed $middlewares): void
    {
        $method = strtoupper($method);
        $append = self::normalizeMiddlewareList($middlewares);

        if ($append === []) {
            return;
        }

        if (!isset(self::$middlewares[$method][$path])) {
            self::$middlewares[$method][$path] = [];
        }

        self::$middlewares[$method][$path] = array_merge(self::$middlewares[$method][$path], $append);
    }

    public static function registerRouteName(string $method, string $path, string $name, string $prefix = ''): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Route name cannot be empty.');
        }

        $method = strtoupper($method);

        if (!isset(self::$routes[$method][$path])) {
            throw new InvalidArgumentException(sprintf('Route [%s %s] is not registered.', $method, $path));
        }

        $prefix = trim($prefix);
        if ($prefix !== '') {
            $prefix = rtrim($prefix, '.');
            if ($prefix !== '') {
                $name = $prefix . '.' . ltrim($name, '.');
            }
        }

        $current = self::$routes[$method][$path]['name'] ?? null;

        if ($current === $name) {
            return;
        }

        if (isset(self::$namedRoutes[$name]) && (self::$namedRoutes[$name]['method'] !== $method || self::$namedRoutes[$name]['path'] !== $path)) {
            throw new InvalidArgumentException(sprintf('Route name [%s] is already in use.', $name));
        }

        if ($current !== null && isset(self::$namedRoutes[$current])) {
            unset(self::$namedRoutes[$current]);
        }

        self::$routes[$method][$path]['name'] = $name;
        self::$namedRoutes[$name] = [
            'method' => $method,
            'path' => $path,
        ];
    }

    public static function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        if (!isset(self::$namedRoutes[$name])) {
            throw new InvalidArgumentException(sprintf('Route [%s] is not defined.', $name));
        }

        $definition = self::$namedRoutes[$name];
        $uri = self::substituteRouteParameters($definition['path'], $parameters);

        if (preg_match('/\{[^}]+\}/', $uri)) {
            throw new InvalidArgumentException(sprintf('Route [%s] is missing required parameters.', $name));
        }

        $uri = $uri === '' ? '/' : '/' . ltrim($uri, '/');

        if (!empty($parameters)) {
            $query = http_build_query($parameters);
            if ($query !== '') {
                $uri .= '?' . $query;
            }
        }

        if (! $absolute) {
            return $uri;
        }

        $base = rtrim((string) env('APP_URL', ''), '/');

        if ($base === '') {
            return $uri;
        }

        return $base . ($uri === '/' ? '/' : $uri);
    }

    private static function substituteRouteParameters(string $uri, array &$parameters): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use (&$parameters): string {
            $key = $matches[1];

            if (!array_key_exists($key, $parameters)) {
                throw new InvalidArgumentException(sprintf('Missing parameter [%s] for route generation.', $key));
            }

            $value = $parameters[$key];
            unset($parameters[$key]);

            return rawurlencode((string) $value);
        }, $uri);
    }

    private static function normalizeMiddleware(mixed $definition): array
    {
        if (is_array($definition)) {
            if (empty($definition)) {
                throw new InvalidArgumentException('Middleware definition cannot be an empty array.');
            }

            $class = array_shift($definition);
            if (!is_string($class)) {
                throw new InvalidArgumentException('Middleware class name must be a string.');
            }

            return [$class, array_values($definition)];
        }

        if (!is_string($definition)) {
            throw new InvalidArgumentException('Invalid middleware definition.');
        }

        return [$definition, []];
    }
}

final class RouteDefinition
{
    public function __construct(private string $method, private string $path, private string $namePrefix)
    {
    }

    public function name(string $name): self
    {
        Router::registerRouteName($this->method, $this->path, $name, $this->namePrefix);

        return $this;
    }

    public function middleware(mixed $middlewares): self
    {
        Router::appendRouteMiddleware($this->method, $this->path, $middlewares);

        return $this;
    }
}
