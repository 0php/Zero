<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Scheduling;

use Closure;
use ReflectionFunction;
use ReflectionMethod;

final class CallbackEvent extends Event
{
    /** @var callable */
    private $callback;

    private ?string $overrideDescription;

    public function __construct(callable $callback, ?string $description = null)
    {
        parent::__construct();

        $this->callback = $callback;
        $this->overrideDescription = $description;
        $this->identifier = $this->resolveIdentifier($callback, $description);
    }

    protected function execute(Scheduler $scheduler, \DateTimeInterface $now): void
    {
        ($this->callback)();
    }

    protected function defaultDescription(): string
    {
        return $this->overrideDescription ?? 'Callback task';
    }

    private function resolveIdentifier(callable $callback, ?string $description): string
    {
        if ($description !== null && $description !== '') {
            return 'callback-desc:' . $description;
        }

        if ($callback instanceof Closure) {
            $reflection = new ReflectionFunction($callback);
            $file = $reflection->getFileName() ?? 'unknown';
            $line = $reflection->getStartLine();

            return 'closure:' . $file . ':' . $line;
        }

        if (is_array($callback)) {
            [$target, $method] = $callback;
            $class = is_object($target) ? $target::class : (string) $target;

            return 'callable:' . $class . '::' . $method;
        }

        if (is_string($callback)) {
            return 'function:' . $callback;
        }

        if (is_object($callback) && method_exists($callback, '__invoke')) {
            $reflection = new ReflectionMethod($callback, '__invoke');
            $file = $reflection->getFileName() ?? 'unknown';
            $line = $reflection->getStartLine();

            return 'invokable:' . $reflection->getDeclaringClass()->getName() . ':' . $file . ':' . $line;
        }

        return 'callback:' . spl_object_id((object) $callback);
    }
}
