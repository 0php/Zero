<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use FilesystemIterator;
use Zero\Lib\Console\Command\CommandInterface;

final class CacheClearCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'cache:clear';
    }

    public function getDescription(): string
    {
        return 'Clear compiled view cache files.';
    }

    public function getUsage(): string
    {
        return 'php zero cache:clear';
    }

    public function execute(array $argv): int
    {
        $cacheDir = base('storage/framework/views/cache');

        if (!is_dir($cacheDir)) {
            echo 'View cache directory does not exist — nothing to clear.' . PHP_EOL;
            return 0;
        }

        $removed = 0;
        $iterator = new FilesystemIterator($cacheDir, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            if (@unlink($item->getPathname())) {
                $removed++;
            }
        }

        if ($removed === 0) {
            echo 'View cache is already empty.' . PHP_EOL;
        } else {
            echo sprintf('Cleared %d cached view file(s).%s', $removed, PHP_EOL);
        }

        return 0;
    }
}
