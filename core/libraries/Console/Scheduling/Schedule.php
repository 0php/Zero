<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Scheduling;

final class Schedule
{
    /**
     * @var array<int, Event>
     */
    private array $events = [];

    private static ?self $global = null;

    public static function instance(): self
    {
        return self::$global ??= new self();
    }

    public static function reset(): void
    {
        self::$global = new self();
    }

    public static function command(string $signature, array $arguments = []): CommandEvent
    {
        return self::instance()->registerCommand($signature, $arguments);
    }

    public static function call(callable $callback, ?string $description = null): CallbackEvent
    {
        return self::instance()->registerCallback($callback, $description);
    }

    /**
     * @return array<int, Event>
     */
    public function events(): array
    {
        return $this->events;
    }

    public function isEmpty(): bool
    {
        return $this->events === [];
    }

    public function __call(string $name, array $arguments)
    {
        return match ($name) {
            'command' => $this->registerCommand(...$arguments),
            'call' => $this->registerCallback(...$arguments),
            default => throw new \BadMethodCallException(sprintf('Schedule method %s does not exist.', $name)),
        };
    }

    private function registerCommand(string $signature, array $arguments = []): CommandEvent
    {
        $event = new CommandEvent($signature, $arguments);
        $this->events[] = $event;

        return $event;
    }

    private function registerCallback(callable $callback, ?string $description = null): CallbackEvent
    {
        $event = new CallbackEvent($callback, $description);
        $this->events[] = $event;

        return $event;
    }
}
