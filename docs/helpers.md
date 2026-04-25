# Helpers

Helpers in Zero come in two flavours:

1. **Built-in global functions** — shipped with the framework, always available (path helpers, HTTP helpers, facade shortcuts, functional utilities). See the [reference table](#built-in-global-functions) below.
2. **Application helpers** — custom classes you write under `App\Helpers`, registered via `RegistersHelpers`. The bulk of this document covers writing those.

## Built-in global functions

These are loaded by `core/kernel.php` and are always available in both HTTP and CLI contexts.

### Path helpers

All accept an optional sub-path argument and return an absolute path.

| Function | Resolves to |
| --- | --- |
| `base($path = '')` | Project root (`BASE_PATH`) |
| `app_path($path)` | `app/` |
| `core_path($path)` | `core/` |
| `lib_path($path)` | `core/libraries/` |
| `config_path($path)` | `config/` |
| `database_path($path)` | `database/` |
| `resource_path($path)` | `resources/` |
| `view_path($path)` | `resources/views/` (alias: `viewpath()`) |
| `lang_path($path)` | `resources/i18n/` |
| `public_path($path)` | `public/` |
| `storage_path($path)` | `storage/` |
| `cache_path($path)` | `storage/cache/` |
| `log_path($path)` | `storage/logs/` |

### HTTP & framework

| Function | Purpose |
| --- | --- |
| `view($template, $data = [], $status = 200, $headers = [])` | Render a Blade-style view into an HTML `Response` |
| `response($value = null, $status = 200, $headers = [])` | Build a `Response` from any value (string, array/object → JSON, etc.) |
| `redirect($location = null, $status = 302, $headers = [])` | Build a redirect `Response`; with no location, redirects back |
| `back($fallback = '/', $status = 302, $headers = [])` | Redirect to the previous URL |
| `route($name, $parameters = [], $absolute = true)` | Resolve a named route URL |
| `url($path = '', $query = [])` | Build an absolute URL using the current host |
| `asset($path)` | URL for a public asset |
| `request($key = null, $default = null)` | Current `Request` instance, or input value when `$key` given |
| `auth()` | Current authenticated user (or `false`) |
| `session($key = null, $default = null)` | Read/write the session (`array` form sets multiple keys) |
| `old($key = null, $default = null)` | Retrieve flashed input |
| `abort($status, $message = '', $headers = [])` | Throw an HTTP exception with the given status |
| `abort_if($cond, $status, ...)` / `abort_unless($cond, $status, ...)` | Conditional aborts |
| `logger($message = null, $context = [])` | Write a debug log entry, or get the `Log` class |
| `dd(...$values)` | Pretty-dump and exit. Format adapts to the request: CLI prints an ANSI-coloured panel; HTTP requests with `Accept: application/json`, `Accept: */+json`, `Content-Type: application/json`, or `X-Requested-With: XMLHttpRequest` get a JSON dump (status 500); other HTTP requests get an HTML debug page. |
| `dump(...$values)` | Pretty-dump without exiting |

### Configuration & i18n

| Function | Purpose |
| --- | --- |
| `config($key)` | Dot-notation read from `config/*.php` |
| `env($key, $default = null)` | Read from `.env` (with override chain) |
| `__($key, $replacements = [], $context = null, $locale = null)` | Translate a key |
| `locale($context = null)` | Current locale |
| `locales($context = null)` | Configured locales |
| `set_locale($locale, $context = null, $persist = true)` | Switch locale at runtime |

### Date & functional

| Function | Purpose |
| --- | --- |
| `now()` | `Date` instance for the current moment |
| `today()` | `Date` instance for the start of today |
| `value($value, ...$args)` | Invoke if Closure, otherwise return as-is |
| `tap($value, $cb = null)` | Run a side effect, return the value |

### Support shortcuts

| Function | Purpose |
| --- | --- |
| `collect($items = [])` | Build a `Collection` |
| `str($value = null)` | Fluent `Stringable` (or returns the `Str` class when called with no args) |

See [docs/support.md](support.md) for the full surface of `Str`, `Stringable`, `Arr`, `Collection`, and `Number`.

### Examples

Path helpers:

```php
base();                         // '/var/www/app'
base('public/index.php');       // '/var/www/app/public/index.php'
app_path('controllers');        // '/var/www/app/app/controllers'
core_path('bootstrap.php');     // '/var/www/app/core/bootstrap.php'
lib_path('Http');               // '/var/www/app/core/libraries/Http'
config_path('mail.php');        // '/var/www/app/config/mail.php'
database_path('migrations');    // '/var/www/app/database/migrations'
resource_path('views');         // '/var/www/app/resources/views'
view_path('home.php');          // '/var/www/app/resources/views/home.php'
lang_path('en');                // '/var/www/app/resources/i18n/en'
public_path('css/app.css');     // '/var/www/app/public/css/app.css'
storage_path('cache');          // '/var/www/app/storage/cache'
cache_path('views');            // '/var/www/app/storage/cache/views'
log_path('app.log');            // '/var/www/app/storage/logs/app.log'
```

HTTP & framework:

```php
return view('users.show', ['user' => $user]);                  // HTML response
return view('users.show', ['user' => $user], 200, ['X-A' => 'B']);

return response('OK');                                          // text Response
return response(['ok' => true], 201);                           // JSON Response
return response($user);                                         // model → JSON

return redirect('/login');                                      // 302
return redirect();                                              // back to referer
return back('/dashboard');                                      // back, fallback /dashboard

route('users.show', ['id' => 42]);                              // '/users/42'
route('users.show', ['id' => 42], absolute: true);              // 'http://x/users/42'

url('search', ['q' => 'php']);                                  // 'http://x/search?q=php'
asset('css/app.css');                                           // 'http://x/css/app.css'

$req   = request();                                             // Request instance
$email = request('email');                                      // input value
$page  = request('page', 1);

$user  = auth();                                                // current user (or false)

session('flash.success');                                       // read
session(['flash' => ['success' => 'Saved']]);                   // bulk write
session('missing', 'fallback');                                 // with default

old('email');                                                   // last submitted value
old('email', '');

abort(404);
abort(403, 'Forbidden');
abort_if(! $user->isAdmin(), 403);
abort_unless($user, 401);

logger('user signed in', ['id' => $user->id]);                  // log debug entry
$logClass = logger();                                           // returns Log::class

dd($user, $request->all());                                     // dump and exit
dump($user);                                                    // dump, keep going
```

Configuration & i18n:

```php
config('view.cache_enabled');                                   // dot-notation
config('mail.smtp.host');

env('APP_ENV', 'production');                                   // .env value
env('AUTH_TOKEN_TTL');

__('common.welcome');                                           // translate
__('greeting.hello', ['name' => 'Tofik']);

locale();                                                       // 'en'
locales();                                                      // ['en', 'id', 'fr']

set_locale('id');                                               // persist via config
set_locale('id', persist: false);                               // runtime only
```

Date & functional:

```php
now();                                                          // Date::now()
now()->addDays(7);

today();                                                        // start of today

value(42);                                                      // 42
value(fn () => expensive());                                    // calls Closure
value($maybeClosure, $arg1, $arg2);                             // forwards args

tap($user, fn ($u) => logger('saving', ['id' => $u->id]))->save();
```

Support shortcuts:

```php
collect([1, 2, 3])->sum();                                      // 6
collect($users)->where('active', true)->pluck('email');

(string) str('Hello {name}')->swap(['{name}' => 'World']);      // 'Hello World'
(string) str('users.profile-photo')->replaceLast('.', '/');     // 'users/profile-photo'
str();                                                          // returns Str::class
```

## Application helpers

Application helpers let you expose reusable project-specific functionality as globally available functions that can be invoked from controllers, views, CLI scripts, or any other part of your application. They mirror Laravel-style helpers while remaining lightweight and explicit.

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

use Zero\Lib\Support\RegistersHelpers;

class Helper
{
    use RegistersHelpers;

    public function boot(): void
    {
        $this->register([
            \App\Helpers\RandomText::class,
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

The generator now updates `app/helpers/Helper.php` for you, appending the new helper class to the registration list.

## Runtime Guardrails

`Zero\\Lib\\Support\\HelperRegistry::register()` (and the `RegistersHelpers` trait) validate helper classes before wiring them:

- Ensures the class exists, is instantiable, and exposes a public `handle()` method.
- Reads the signature via an accessor (`getSignature()`/`signature()`) or a `$signature` property.
- Normalises CLI/HTTP flags and skips registration when the current runtime is disabled.
- Prevents collisions by checking whether a helper with the same signature has already been registered or if the function already exists.

Helpers are registered only once per execution thanks to the static guard inside `bootApplicationHelpers()`.

## Tips

- Keep helper logic focused and side-effect free; use services or models for heavy lifting.
- Return values directly—helpers pipe the result of `handle()` back to the global function call.
- When a helper depends on framework services, resolve them inside `handle()` to ensure they are available in both HTTP and CLI contexts.

By structuring helpers this way, you gain globally accessible utilities without sacrificing testability or framework boot order guarantees.
