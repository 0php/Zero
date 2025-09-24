<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;

final class KeyGenerateCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'key:generate';
    }

    public function getDescription(): string
    {
        return 'Set the application key';
    }

    public function getUsage(): string
    {
        return 'php zero key:generate [--show]';
    }

    public function execute(array $argv): int
    {
        $show = in_array('--show', $argv, true);
        $key = $this->generateKey();

        if ($show) {
            echo $key . "\n";
            return 0;
        }

        // Get the project root directory
        $rootDir = getcwd();
        $envFile = $rootDir . '/.env';
        $envExampleFile = $rootDir . '/.env.example';
        
        if (!file_exists($envFile)) {
            \Zero\Lib\Log::channel('internal')->info('Creating new .env file...');
            
            // If .env.example exists, use it as a template
            if (file_exists($envExampleFile)) {
                if (!copy($envExampleFile, $envFile)) {
                    \Zero\Lib\Log::channel('internal')->error('Failed to create .env from .env.example. Check file permissions.');
                    return 1;
                }
                \Zero\Lib\Log::channel('internal')->info('Copied .env from .env.example');
            } else {
                // Create an empty .env file if .env.example doesn't exist
                if (file_put_contents($envFile, "") === false) {
                    \Zero\Lib\Log::channel('internal')->error('Failed to create .env file. Check directory permissions.');
                    return 1;
                }
            }
        }

        $envContent = file_get_contents($envFile);
        
        // Replace existing APP_KEY or add it if it doesn't exist
        if (str_contains($envContent, 'APP_KEY=')) {
            $envContent = preg_replace(
                '/^APP_KEY=.*$/m',
                'APP_KEY=' . $key,
                $envContent
            );
        } else {
            $envContent .= "\nAPP_KEY=" . $key . "\n";
        }

        if (file_put_contents($envFile, $envContent) === false) {
            \Zero\Lib\Log::channel('internal')->error("Failed to write to $envFile. Check file permissions.");
            return 1;
        }

        \Zero\Lib\Log::channel('internal')->info("Application key set successfully in $envFile");
        return 0;
    }

    private function generateKey(): string
    {
        return 'base64:' . base64_encode(
            random_bytes(32)
        );
    }
}
