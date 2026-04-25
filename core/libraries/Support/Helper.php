<?php

declare(strict_types=1);

namespace Zero\Lib\Support {

final class HelperRegistry
{
    private const REGISTRY_KEY = '__zero_registered_helpers';

    /**
     * @param array<int, class-string>|class-string $helpers
     */
    public static function register(array|string $helpers): void
    {
        $helpers = is_array($helpers) ? array_values(array_filter($helpers)) : [$helpers];
        $helpers = array_unique($helpers);

        $resolveProperty = static function (\ReflectionClass $class, object $object, string $property, mixed $default = null): mixed {
            if (! $class->hasProperty($property)) {
                return $default;
            }

            $prop = $class->getProperty($property);

            try {
                if (method_exists($prop, 'isInitialized') && $prop->isInitialized($object)) {
                    return $prop->getValue($object);
                }

                if ($prop->hasDefaultValue()) {
                    return $prop->getDefaultValue();
                }

                return $prop->getValue($object);
            } catch (\Throwable) {
                return $default;
            }
        };

        foreach ($helpers as $helperClass) {
            if (! is_string($helperClass) || $helperClass === '') {
                continue;
            }

            if (! class_exists($helperClass)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($helperClass);
            } catch (\ReflectionException) {
                continue;
            }

            if ($reflection->isAbstract() || ! $reflection->hasMethod('handle')) {
                continue;
            }

            $handleMethod = $reflection->getMethod('handle');

            if (! $handleMethod->isPublic()) {
                continue;
            }

            try {
                $instance = $reflection->newInstance();
            } catch (\Throwable) {
                continue;
            }

            if (! is_callable([$instance, 'handle'])) {
                continue;
            }

            $signature = null;

            foreach (['getSignature', 'signature'] as $methodName) {
                if (! $reflection->hasMethod($methodName)) {
                    continue;
                }

                $method = $reflection->getMethod($methodName);

                if (! $method->isPublic() || $method->getNumberOfRequiredParameters() !== 0) {
                    continue;
                }

                try {
                    $signature = $method->invoke($instance);
                } catch (\Throwable) {
                    $signature = null;
                }

                if ($signature !== null) {
                    break;
                }
            }

            if ($signature === null) {
                $signature = $resolveProperty($reflection, $instance, 'signature');
            }

            if (! is_string($signature) || $signature === '') {
                continue;
            }

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $signature)) {
                continue;
            }

            $cliAllowed = $resolveProperty($reflection, $instance, 'cli', true);
            $webAllowed = $resolveProperty($reflection, $instance, 'web', true);

            $cliAllowed = $cliAllowed === null ? true : (bool) $cliAllowed;
            $webAllowed = $webAllowed === null ? true : (bool) $webAllowed;

            $isCli = PHP_SAPI === 'cli';

            if ($isCli && ! $cliAllowed) {
                continue;
            }

            if (! $isCli && ! $webAllowed) {
                continue;
            }

            if (! isset($GLOBALS[self::REGISTRY_KEY])) {
                $GLOBALS[self::REGISTRY_KEY] = [];
            }

            if (isset($GLOBALS[self::REGISTRY_KEY][$signature])) {
                continue;
            }

            $GLOBALS[self::REGISTRY_KEY][$signature] = static function (...$arguments) use ($instance) {
                return $instance->handle(...$arguments);
            };

            if (function_exists($signature)) {
                continue;
            }

            $functionBody = sprintf(
                'function %s(...$arguments) { return ($GLOBALS[\'%s\'][\'%s\'])(...$arguments); }',
                $signature,
                self::REGISTRY_KEY,
                $signature
            );

            eval($functionBody);
        }
    }
}

trait RegistersHelpers
{
    protected function register(array|string $helpers): void
    {
        HelperRegistry::register($helpers);
    }

    /**
     * @deprecated Use register() instead.
     */
    protected function registerHelper(array|string $helpers): void
    {
        $this->register($helpers);
    }
}

}

namespace {

use Zero\Lib\Auth\Auth;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\Log;
use Zero\Lib\Router;
use Zero\Lib\Session;
use Zero\Lib\Support\Collection;
use Zero\Lib\Support\Date;
use Zero\Lib\Support\Str;
use Zero\Lib\Support\Stringable;
use Zero\Lib\View;

if (!function_exists('response')) {
    /**
     * Create a response instance from arbitrary data.
     */
    function response(mixed $value = null, int $status = 200, array $headers = []): Response
    {
        if ($value instanceof Response) {
            if (! empty($headers)) {
                $value->withHeaders($headers);
            }

            if ($status !== $value->getStatus()) {
                $value->status($status);
            }

            return $value;
        }

        if ($value === null) {
            return Response::noContent($status === 200 ? 204 : $status, $headers);
        }

        if (is_array($value) || $value instanceof \JsonSerializable || $value instanceof \Traversable || is_object($value)) {
            return Response::json($value, $status, $headers);
        }

        if (is_bool($value)) {
            return Response::json($value, $status, $headers);
        }

        return Response::text((string) $value, $status, $headers);
    }
}

if (!function_exists('view')) {
    /**
     * Render a view into an HTML response.
     */
    function view(string $template, array $data = [], int $status = 200, array $headers = []): Response
    {
        $content = View::render($template, $data);

        return Response::html($content, $status, $headers);
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL for the given named route.
     */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return Router::route($name, $parameters, $absolute);
    }
}

if (!function_exists('redirect')) {
    /**
     * Build a redirect response. With no arguments, returns a 302 to the previous URL.
     */
    function redirect(?string $location = null, int $status = 302, array $headers = []): Response
    {
        if ($location === null) {
            return Response::redirectBack('/', $status, $headers);
        }

        return Response::redirect($location, $status, $headers);
    }
}

if (!function_exists('back')) {
    /**
     * Redirect to the previous URL (or fallback if no referer).
     */
    function back(string $fallback = '/', int $status = 302, array $headers = []): Response
    {
        return Response::redirectBack($fallback, $status, $headers);
    }
}

if (!function_exists('request')) {
    /**
     * Access the current request, or read an input value when a key is given.
     */
    function request(?string $key = null, mixed $default = null): mixed
    {
        $request = Request::instance();

        if ($key === null) {
            return $request;
        }

        return Request::get($key, $default);
    }
}

if (!function_exists('auth')) {
    /**
     * Access the Auth facade. Returns the current user when called with no arguments.
     */
    function auth(): mixed
    {
        return Auth::user();
    }
}

if (!function_exists('session')) {
    /**
     * Read or write the session. Pass an array to set multiple keys at once.
     */
    function session(string|array|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return null;
        }

        if (is_array($key)) {
            foreach ($key as $name => $value) {
                Session::set($name, $value);
            }
            return null;
        }

        return Session::has($key) ? Session::get($key) : $default;
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve a value from previously flashed input.
     */
    function old(?string $key = null, mixed $default = null): mixed
    {
        $values = Session::get('old');

        if (! is_array($values)) {
            return $key === null ? ($values ?? $default) : $default;
        }

        if ($key === null) {
            return $values;
        }

        return $values[$key] ?? $default;
    }
}

if (!function_exists('logger')) {
    /**
     * Write a debug log entry, or return the Log class when no message is given.
     */
    function logger(?string $message = null, array $context = []): mixed
    {
        if ($message === null) {
            return Log::class;
        }

        Log::debug($message, $context);

        return null;
    }
}

if (!function_exists('abort')) {
    /**
     * Throw an HTTP exception with the given status code.
     */
    function abort(int $status, string $message = '', array $headers = []): never
    {
        $exception = new \RuntimeException($message !== '' ? $message : ('HTTP ' . $status), $status);

        if (function_exists('zero_http_error_response')) {
            zero_http_error_response($status, [
                'message' => $message,
                'headers' => $headers,
                'exception' => $exception,
            ]);
        }

        throw $exception;
    }
}

if (!function_exists('abort_if')) {
    /**
     * Abort with the given status code when the condition is truthy.
     */
    function abort_if(mixed $condition, int $status, string $message = '', array $headers = []): void
    {
        if ($condition) {
            abort($status, $message, $headers);
        }
    }
}

if (!function_exists('abort_unless')) {
    /**
     * Abort with the given status code when the condition is falsy.
     */
    function abort_unless(mixed $condition, int $status, string $message = '', array $headers = []): void
    {
        if (! $condition) {
            abort($status, $message, $headers);
        }
    }
}

if (!function_exists('url')) {
    /**
     * Build an absolute URL for a path within the application.
     */
    function url(string $path = '', array $query = []): string
    {
        $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_ENV['APP_HOST'] ?? 'localhost');
        $base = $scheme . '://' . $host;

        $path = '/' . ltrim($path, '/');
        $url = $base . $path;

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }
}

if (!function_exists('asset')) {
    /**
     * Build a URL for a public asset.
     */
    function asset(string $path): string
    {
        return url($path);
    }
}

if (!function_exists('dump')) {
    /**
     * Pretty-print one or more values without halting execution.
     */
    function dump(mixed ...$values): void
    {
        foreach ($values as $value) {
            echo PHP_SAPI === 'cli' ? '' : '<pre>';
            var_dump($value);
            echo PHP_SAPI === 'cli' ? '' : '</pre>';
        }
    }
}

if (!function_exists('now')) {
    /**
     * Get a Date instance representing the current moment.
     */
    function now(): Date
    {
        return Date::now();
    }
}

if (!function_exists('today')) {
    /**
     * Get a Date instance representing the start of today.
     */
    function today(): Date
    {
        return Date::parse(date('Y-m-d'));
    }
}

if (!function_exists('value')) {
    /**
     * Return the result of a closure, or the value itself if not callable.
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('tap')) {
    /**
     * Call a callback with the given value, then return the value.
     */
    function tap(mixed $value, ?callable $callback = null): mixed
    {
        if ($callback !== null) {
            $callback($value);
        }

        return $value;
    }
}

if (!function_exists('collect')) {
    /**
     * Build a Collection from the given iterable.
     */
    function collect(mixed $items = []): Collection
    {
        return Collection::make($items === null ? [] : (is_iterable($items) ? $items : [$items]));
    }
}

if (!function_exists('str')) {
    /**
     * Get a fluent Stringable instance, or return the Str class when called with no args.
     */
    function str(?string $value = null): Stringable|string
    {
        if ($value === null) {
            return Str::class;
        }
        return Str::of($value);
    }
}

if (!function_exists('bootApplicationHelpers')) {
    /**
     * Boot all application helper classes once per request/CLI execution.
     */
    function bootApplicationHelpers(): void
    {
        static $booted = false;

        if ($booted) {
            return;
        }

        $booted = true;

        if (! class_exists(\App\Helpers\Helper::class)) {
            return;
        }

        try {
            $helper = new \App\Helpers\Helper();
        } catch (\Throwable) {
            return;
        }

        if (! method_exists($helper, 'boot')) {
            return;
        }

        $helper->boot();
    }
}

}
