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

            if (! $prop->isPublic()) {
                $prop->setAccessible(true);
            }

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

use Zero\Lib\Http\Response;
use Zero\Lib\Router;
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
