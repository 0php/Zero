<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Scheduling;

final class CommandEvent extends Event
{
    private string $signature;

    /**
     * @var array<int, string>
     */
    private array $arguments;

    public function __construct(string $signature, array $arguments = [])
    {
        parent::__construct();

        $this->signature = $signature;
        $this->arguments = array_values($arguments);
        $this->identifier = 'command:' . $this->signature . '|' . md5(json_encode($this->arguments, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<int, string> $arguments
     */
    public function withArguments(array $arguments): self
    {
        $this->arguments = array_values($arguments);

        return $this;
    }

    protected function execute(Scheduler $scheduler, \DateTimeInterface $now): void
    {
        $scheduler->runCommand($this->signature, $this->arguments);
    }

    protected function defaultDescription(): string
    {
        return 'Command: ' . $this->signature;
    }
}
