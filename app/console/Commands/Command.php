<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Zero\Lib\Console\Application;
use Zero\Lib\Support\RegistersConsoleCommands;

class Command
{
    use RegistersConsoleCommands;

    public function boot(Application $app): void
    {
        $this->register($app, [
            // \App\Console\Commands\ExampleCommand::class,
        ]);
    }
}
