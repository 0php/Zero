<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use InvalidArgumentException;
use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Storage\StorageManager;

final class StorageLinkCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'storage:link';
    }

    public function getDescription(): string
    {
        return 'Create symbolic links for configured storage disks';
    }

    public function getUsage(): string
    {
        return 'php zero storage:link';
    }

    public function execute(array $argv): int
    {
        $links = $this->configuredLinks();

        if (empty($links)) {
            \Zero\Lib\Log::channel('internal')->info('No storage links defined.');

            return 0;
        }

        $status = 0;

        foreach ($links as $link => $targetDisk) {
            try {
                $this->createLink($link, $targetDisk);
                \Zero\Lib\Log::channel('internal')->info(sprintf('Linked [%s] -> [%s]', $link, $targetDisk));
            } catch (\Throwable $e) {
                $status = 1;
                \Zero\Lib\Log::channel('internal')->error(sprintf('Failed to link [%s]: %s', $link, $e->getMessage()));
            }
        }

        return $status;
    }

    /**
     * @return array<string, string>
     */
    private function configuredLinks(): array
    {
        $links = config('storage.links') ?? [];

        if (!is_array($links)) {
            throw new InvalidArgumentException('storage.links configuration must be an array.');
        }

        if ($links === []) {
            return $links;
        }

        $manager = new StorageManager();
        $resolved = [];

        foreach ($links as $link => $definition) {
            if ($definition === null || $definition === '') {
                continue;
            }

            $root = null;

            if (is_string($definition)) {
                try {
                    $config = $manager->getDiskConfig($definition);
                    $root = $config['root'] ?? storage_path();
                } catch (InvalidArgumentException) {
                    $root = $definition;
                }
            }

            if ($root === null) {
                continue;
            }

            $resolved[$link] = $root;
        }

        return $resolved;
    }

    private function createLink(string $link, string $target): void
    {
        $link = rtrim($link, DIRECTORY_SEPARATOR);
        $target = rtrim($target, DIRECTORY_SEPARATOR);

        if (!file_exists($target)) {
            if (!@mkdir($target, 0775, true) && !is_dir($target)) {
                throw new InvalidArgumentException(sprintf('Target path [%s] does not exist and could not be created.', $target));
            }
        }

        if (is_link($link)) {
            $existing = readlink($link);
            if ($existing === $target) {
                return;
            }

            if ($existing !== false) {
                unlink($link);
            }
        }

        if (file_exists($link)) {
            throw new InvalidArgumentException(sprintf('Link location [%s] already exists and is not a symlink.', $link));
        }

        $directory = dirname($link);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new InvalidArgumentException(sprintf('Unable to create link directory [%s].', $directory));
        }

        if (!@symlink($target, $link)) {
            throw new InvalidArgumentException(sprintf('Unable to create symlink [%s] -> [%s] (check permissions).', $link, $target));
        }
    }
}
