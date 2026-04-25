<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

trait Conditional
{
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    public function tap(callable $callback): static
    {
        $callback($this);
        return $this;
    }

    public function when(mixed $condition, callable $callback, ?callable $default = null): static
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($default !== null) {
            $default($this, $condition);
        }
        return $this;
    }

    public function unless(mixed $condition, callable $callback, ?callable $default = null): static
    {
        return $this->when(! $condition, $callback, $default);
    }

    public function whenEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    public function whenNotEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }
}
