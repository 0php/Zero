# Helpers

Helpers let you expose reusable functionality as globally available functions that can be invoked from controllers, views, CLI scripts, or any other part of your application. They mirror Laravel-style helpers while remaining lightweight and explicit.

## Anatomy of a Helper

Each helper is a simple PHP class living under the `App\Helpers` namespace. The class must provide a public `handle()` method which receives the arguments passed to the helper function. Optionally, the class can expose metadata via properties:

- `protected string $signature` — the global function name to register. Defaults to the snake_case version of the class name when generated with the CLI.
- `protected bool $cli` — whether the helper can be invoked in CLI contexts (defaults to `true`).
- `protected bool $web` — whether the helper can be invoked during HTTP requests (defaults to `true`).

Example:

```php
<?php

namespace App\Helpers;

class RandomText
{
    protected string $signature = 'random_text';
    protected bool $cli = true;
    protected bool $web = true;

    public function handle(int $length = 16): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random = '';

        for ($i = 0; $i < $length; $i++) {
            $random .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $random;
    }
}
```

Once registered, call the helper from anywhere:

```php
$token = random_text(40);
```

## Registering Helpers

Centralise helper registration inside `app/helpers/Helper.php`:

```php
<?php

namespace App\Helpers;

class Helper
{
    public function boot(): void
    {
        registerHelper([
            RandomText::class,
            // Add other helper classes here...
        ]);
    }
}
```

The framework invokes `boot()` automatically on every HTTP request and CLI execution via `bootApplicationHelpers()`. You can safely reference models, facades, configuration, or any other framework services inside helper classes because registration happens after the kernel has been fully bootstrapped.

## Creating Helpers via CLI

Scaffold a helper with the built-in generator:

```bash
php zero make:helper randomText
```

This command creates `app/helpers/RandomText.php` using `core/templates/helper.tmpl`. The template sets a sensible default signature derived from the class name, exposes the `$cli` and `$web` flags, and stubs the `handle()` method. Pass `--force` to overwrite an existing helper stub.

After generation, add the new class to the array in `app/helpers/Helper.php` so it is registered automatically.

## Runtime Guardrails

`registerHelper()` validates helper classes before wiring them:

- Ensures the class exists, is instantiable, and exposes a public `handle()` method.
- Reads the signature via an accessor (`getSignature()`/`signature()`) or a `$signature` property.
- Normalises CLI/HTTP flags and skips registration when the current runtime is disabled.
- Prevents collisions by checking whether a helper with the same signature has already been registered or if the function already exists.

Helpers are registered only once per execution thanks to the static guard inside `bootApplicationHelpers()`.

## Tips

- Keep helper logic focused and side-effect free; use services or models for heavy lifting.
- Return values directly—`registerHelper()` pipes the result of `handle()` back to the global function call.
- When a helper depends on framework services, resolve them inside `handle()` to ensure they are available in both HTTP and CLI contexts.

By structuring helpers this way, you gain globally accessible utilities without sacrificing testability or framework boot order guarantees.
