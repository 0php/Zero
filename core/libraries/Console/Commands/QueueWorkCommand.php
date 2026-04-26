<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Throwable;
use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Input;
use Zero\Lib\Log;
use Zero\Lib\Queue\QueueManager;
use Zero\Lib\Queue\Worker;
use Zero\Lib\Queue\WorkerOptions;

final class QueueWorkCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'queue:work';
    }

    public function getDescription(): string
    {
        return 'Pull jobs off a queue and process them.';
    }

    public function getUsage(): string
    {
        return 'php zero queue:work [--connection=] [--queue=default] [--tries=1] [--backoff=0] [--sleep=3] [--once]';
    }

    public function execute(array $argv, ?Input $input = null): int
    {
        $input ??= new Input([], []);

        $connection = (string) ($input->option('connection') ?? $this->valueFromArgv($argv, '--connection') ?? QueueManager::defaultConnection());
        $queueOpt = (string) ($input->option('queue') ?? $this->valueFromArgv($argv, '--queue') ?? QueueManager::defaultQueue($connection));
        $tries = (int) ($input->option('tries') ?? $this->valueFromArgv($argv, '--tries') ?? 1);
        $backoff = (int) ($input->option('backoff') ?? $this->valueFromArgv($argv, '--backoff') ?? 0);
        $sleep = (int) ($input->option('sleep') ?? $this->valueFromArgv($argv, '--sleep') ?? 3);
        $once = $input->hasOption('once') || in_array('--once', $argv, true);

        $queues = array_values(array_filter(array_map('trim', explode(',', $queueOpt))));
        if ($queues === []) {
            $queues = [QueueManager::defaultQueue($connection)];
        }

        $options = new WorkerOptions(
            connection: $connection,
            queues: $queues,
            tries: max(1, $tries),
            backoff: max(0, $backoff),
            sleep: max(1, $sleep),
            once: $once,
        );

        Log::channel('internal')->info('Queue worker starting.', [
            'connection' => $connection,
            'queues' => $queues,
            'once' => $once,
        ]);

        try {
            $driver = QueueManager::driver($connection);
        } catch (Throwable $exception) {
            fwrite(STDERR, sprintf("Failed to resolve queue driver: %s\n", $exception->getMessage()));

            return 1;
        }

        $worker = new Worker($driver);

        return $worker->run($options);
    }

    private function valueFromArgv(array $argv, string $option): ?string
    {
        foreach ($argv as $argument) {
            if (! str_starts_with($argument, $option . '=')) {
                continue;
            }

            return substr($argument, strlen($option) + 1) ?: null;
        }

        return null;
    }
}
