<?php

declare(strict_types=1);

namespace Zero\Lib\Support;

use BadMethodCallException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final class Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (!method_exists(Str::class, $method)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', Str::class, $method));
        }

        $reflection = new ReflectionMethod(Str::class, $method);

        if ($reflection->getNumberOfParameters() === 0) {
            throw new BadMethodCallException(sprintf('Method %s::%s is not chainable.', Str::class, $method));
        }

        $firstParameter = $reflection->getParameters()[0];
        $type = $firstParameter->getType();

        if ($type !== null && !self::typeAllowsString($type)) {
            throw new BadMethodCallException(sprintf('Method %s::%s cannot be chained from Stringable.', Str::class, $method));
        }

        $result = Str::$method($this->value, ...$parameters);

        if (is_string($result)) {
            return new self($result);
        }

        return $result;
    }

    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    private static function typeAllowsString(ReflectionType $type): bool
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName() === 'string' || $type->getName() === 'mixed';
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $innerType) {
                if (self::typeAllowsString($innerType)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
}
