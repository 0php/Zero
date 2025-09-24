<?php

declare(strict_types=1);

namespace Zero\Lib\Console;

final class Input
{
    /** @var array<int, string> */
    private array $arguments;

    /** @var array<string, mixed> */
    private array $options;

    /**
     * @param array<int, string> $arguments
     * @param array<string, mixed> $options
     */
    public function __construct(array $arguments, array $options)
    {
        $this->arguments = array_values($arguments);
        $this->options = $options;
    }

    /**
     * @return array<int, string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function argument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }
}
