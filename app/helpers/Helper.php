<?php

namespace App\Helpers;

class Helper {
    /**
     * Register all helper classes with the global helper registry.
     */
    public function boot(): void {
        registerHelper([
            // YourHelper::class,
        ]);
    }
}
