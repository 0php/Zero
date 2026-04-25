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

    public function tap(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    public function append(string ...$values): self
    {
        return new self($this->value . implode('', $values));
    }

    public function prepend(string ...$values): self
    {
        return new self(implode('', $values) . $this->value);
    }

    public function basename(string $suffix = ''): self
    {
        return new self(basename($this->value, $suffix));
    }

    public function dirname(int $levels = 1): self
    {
        return new self(dirname($this->value, $levels));
    }

    public function classBasename(): self
    {
        $parts = explode('\\', $this->value);
        return new self((string) end($parts));
    }

    public function exactly(string $value): bool
    {
        return $this->value === $value;
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    public function isNotEmpty(): bool
    {
        return $this->value !== '';
    }

    /**
     * @return array<int, string>
     */
    public function explode(string $delimiter, int $limit = PHP_INT_MAX): array
    {
        return explode($delimiter, $this->value, $limit);
    }

    /**
     * @return array<int, string>
     */
    public function split(string $pattern, int $limit = -1, int $flags = 0): array
    {
        $result = preg_split($pattern, $this->value, $limit, $flags);
        return $result === false ? [] : $result;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function scan(string $format): array
    {
        $result = sscanf($this->value, $format);
        return $result === null ? [] : $result;
    }

    public function hash(string $algorithm = 'sha256'): self
    {
        return new self(hash($algorithm, $this->value));
    }

    public function newLine(int $count = 1): self
    {
        return new self($this->value . str_repeat(PHP_EOL, max(0, $count)));
    }

    public function test(string $pattern): bool
    {
        return preg_match($pattern, $this->value) === 1;
    }

    public function stripTags(?string $allowed = null): self
    {
        return new self($allowed === null ? strip_tags($this->value) : strip_tags($this->value, $allowed));
    }

    public function when(mixed $condition, callable $callback, ?callable $default = null): self
    {
        $resolved = is_callable($condition) ? $condition($this) : $condition;
        if ($resolved) {
            $result = $callback($this, $resolved);
        } elseif ($default !== null) {
            $result = $default($this, $resolved);
        } else {
            $result = $this;
        }
        return $result instanceof self ? $result : (is_string($result) ? new self($result) : $this);
    }

    public function unless(mixed $condition, callable $callback, ?callable $default = null): self
    {
        $resolved = is_callable($condition) ? $condition($this) : $condition;
        return $this->when(! $resolved, $callback, $default);
    }

    public function whenEmpty(callable $callback, ?callable $default = null): self
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    public function whenNotEmpty(callable $callback, ?callable $default = null): self
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    public function whenContains(string|array $needles, callable $callback, ?callable $default = null): self
    {
        $needles = is_array($needles) ? $needles : [$needles];
        $matched = false;
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($this->value, $needle)) {
                $matched = true;
                break;
            }
        }
        return $this->when($matched, $callback, $default);
    }

    public function whenStartsWith(string|array $needles, callable $callback, ?callable $default = null): self
    {
        return $this->when(Str::startsWithAny($this->value, (array) $needles), $callback, $default);
    }

    public function whenEndsWith(string|array $needles, callable $callback, ?callable $default = null): self
    {
        return $this->when(Str::endsWithAny($this->value, (array) $needles), $callback, $default);
    }

    public function whenExactly(string $value, callable $callback, ?callable $default = null): self
    {
        return $this->when($this->value === $value, $callback, $default);
    }

    public function whenNotExactly(string $value, callable $callback, ?callable $default = null): self
    {
        return $this->when($this->value !== $value, $callback, $default);
    }

    public function whenIs(string|array $patterns, callable $callback, ?callable $default = null): self
    {
        return $this->when(Str::is($patterns, $this->value), $callback, $default);
    }

    public function whenIsAscii(callable $callback, ?callable $default = null): self
    {
        return $this->when(Str::isAscii($this->value), $callback, $default);
    }

    public function whenIsUuid(callable $callback, ?callable $default = null): self
    {
        return $this->when(Str::isUuid($this->value), $callback, $default);
    }

    public function whenIsUlid(callable $callback, ?callable $default = null): self
    {
        return $this->when(Str::isUlid($this->value), $callback, $default);
    }

    public function whenTest(string $pattern, callable $callback, ?callable $default = null): self
    {
        return $this->when($this->test($pattern), $callback, $default);
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
