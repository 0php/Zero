<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
use Zero\Lib\Model;

/**
 * Encodes a Job instance into a JSON-safe payload and rehydrates it on the
 * worker side. Models passed as constructor args are stored as
 * {__model: Class, key: id} markers and re-fetched via Class::find($id).
 */
final class JobPayload
{
    private const MODEL_MARKER = '__model';

    /**
     * Encode a Job into the wire format stored in the jobs table.
     *
     * @return array{class: class-string<Job>, args: array<int, mixed>, props: array<string, mixed>, tries: int|null, backoff: int|null}
     */
    public static function encode(Job $job): array
    {
        $reflection = new ReflectionClass($job);

        $args = [];
        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $name = $parameter->getName();

                if (! $reflection->hasProperty($name)) {
                    throw new InvalidArgumentException(sprintf(
                        'Job %s constructor parameter $%s must map to a public property of the same name (use constructor property promotion).',
                        $reflection->getName(),
                        $name
                    ));
                }

                $property = $reflection->getProperty($name);
                if (! $property->isPublic()) {
                    throw new InvalidArgumentException(sprintf(
                        'Job %s::$%s must be public so it can be serialized.',
                        $reflection->getName(),
                        $name
                    ));
                }

                $args[] = self::serializeValue($property->isInitialized($job) ? $property->getValue($job) : null);
            }
        }

        $props = [];
        foreach ($reflection->getProperties() as $property) {
            if (! $property->isPublic() || $property->isStatic()) {
                continue;
            }

            if ($constructor !== null) {
                $promoted = false;
                foreach ($constructor->getParameters() as $parameter) {
                    if ($parameter->getName() === $property->getName()) {
                        $promoted = true;
                        break;
                    }
                }

                if ($promoted) {
                    continue;
                }
            }

            if (! $property->isInitialized($job)) {
                continue;
            }

            $props[$property->getName()] = self::serializeValue($property->getValue($job));
        }

        return [
            'class' => $reflection->getName(),
            'args' => $args,
            'props' => $props,
            'tries' => self::readIntProperty($job, 'tries'),
            'backoff' => self::readIntProperty($job, 'backoff'),
        ];
    }

    /**
     * Rehydrate a Job from a previously-encoded payload.
     *
     * @param array{class: class-string<Job>, args: array<int, mixed>, props?: array<string, mixed>} $payload
     */
    public static function decode(array $payload): Job
    {
        $class = $payload['class'] ?? null;
        if (! is_string($class) || ! class_exists($class)) {
            throw new RuntimeException(sprintf('Job class "%s" no longer exists.', (string) $class));
        }

        if (! is_subclass_of($class, Job::class)) {
            throw new RuntimeException(sprintf('Class %s does not implement %s.', $class, Job::class));
        }

        $args = array_map(self::unserializeValue(...), $payload['args'] ?? []);

        /** @var Job $instance */
        $instance = new $class(...$args);

        foreach (($payload['props'] ?? []) as $name => $value) {
            if (! property_exists($instance, $name)) {
                continue;
            }

            $instance->{$name} = self::unserializeValue($value);
        }

        return $instance;
    }

    /**
     * Encode a payload array as a JSON string suitable for storage.
     */
    public static function toJson(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decode a JSON payload string back into an associative array.
     *
     * @return array<string, mixed>
     */
    public static function fromJson(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Job payload JSON did not decode to an array.');
        }

        return $decoded;
    }

    private static function serializeValue(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            throw new InvalidArgumentException('Closures cannot be queued. Wrap the work in a Job class instead.');
        }

        if ($value instanceof Model) {
            return [
                self::MODEL_MARKER => $value::class,
                'key' => $value->getKey(),
            ];
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = self::serializeValue($v);
            }

            return $result;
        }

        if (is_object($value)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot serialize value of type %s for the queue. Pass scalars, arrays, or models only.',
                $value::class
            ));
        }

        return $value;
    }

    private static function unserializeValue(mixed $value): mixed
    {
        if (is_array($value) && array_key_exists(self::MODEL_MARKER, $value)) {
            $class = $value[self::MODEL_MARKER];
            $key = $value['key'] ?? null;

            if (! is_string($class) || ! class_exists($class)) {
                throw new RuntimeException(sprintf('Model class "%s" referenced by the job no longer exists.', (string) $class));
            }

            if (! is_subclass_of($class, Model::class)) {
                throw new RuntimeException(sprintf('Class %s is not a Zero\\Lib\\Model.', $class));
            }

            $model = $class::find($key);
            if ($model === null) {
                throw new RuntimeException(sprintf('Model %s with key %s no longer exists.', $class, var_export($key, true)));
            }

            return $model;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = self::unserializeValue($v);
            }

            return $result;
        }

        return $value;
    }

    private static function readIntProperty(Job $job, string $name): ?int
    {
        if (! property_exists($job, $name)) {
            return null;
        }

        $reflection = new ReflectionClass($job);
        if (! $reflection->hasProperty($name)) {
            return null;
        }

        $property = $reflection->getProperty($name);
        if (! $property->isPublic() || $property->isStatic()) {
            return null;
        }

        if (! $property->isInitialized($job)) {
            return null;
        }

        $value = $property->getValue($job);

        return is_int($value) ? $value : null;
    }
}
