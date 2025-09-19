<?php

declare(strict_types=1);

namespace Zero\Lib\Http\Traits;

trait StreamsResponse
{
    protected bool $streaming = false;
    protected mixed $streamHandler = null;

    public function stream(callable|string $stream): static
    {
        $this->streaming = true;
        $this->streamHandler = $stream;

        return $this;
    }

    protected function outputContent(): void
    {
        if ($this->streaming) {
            $handler = $this->streamHandler;

            if (is_callable($handler)) {
                $handler($this);
            } elseif (is_string($handler)) {
                echo $handler;
            }

            return;
        }

        echo $this->content;
    }
}
