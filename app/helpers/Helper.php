<?php

declare(strict_types=1);

namespace App\Helpers;

use Zero\Lib\Support\RegistersHelpers;

class Helper
{
    use RegistersHelpers;

    /**
     * Register all application helper classes.
     */
    public function boot(): void
    {
        $this->register([
            // \App\Helpers\ExampleHelper::class
        ]);
    }
}
