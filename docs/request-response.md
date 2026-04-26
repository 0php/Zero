# Request & Response

The HTTP layer lives at:

- `Zero\Lib\Http\Request` — composed of concern traits at [`core/libraries/Http/Concerns/`](../core/libraries/Http/Concerns/)
- `Zero\Lib\Http\Response` — see [`core/libraries/Http/Response.php`](../core/libraries/Http/Response.php)

Controllers may return strings, arrays, models, iterables, or explicit `Response` objects; the router normalises everything through `Response::resolve()`.

---

## Request

The router calls `Request::capture()` once per request and injects the same instance into your controllers (where it's also available type-hinted on action signatures). You can also reach it from anywhere via `Request::instance()` or the global `request()` helper.

### Lifecycle

#### `Request::capture(): self`
Snapshot the current PHP request. Called by the router; you generally don't call this yourself.
```php
use Zero\Lib\Http\Request;

$request = Request::capture(); // singleton-like; subsequent calls return the same instance
```

#### `Request::instance(): self`
Return the current request without re-capturing.
```php
$request = Request::instance();
```

#### `Request::replace(array $overrides): self`
Replace the current request (mainly for tests).
```php
Request::replace([
    'method' => 'POST',
    'uri'    => '/users',
    'input'  => ['email' => 'a@b.test'],
]);
```

### Input

#### `->all(): array`
All input — query + form + JSON merged.
```php
$data = $request->all();
```

#### `->input(string $key, mixed $default = null): mixed`
Read a single input value.
```php
$email = $request->input('email');
$page  = $request->input('page', 1);
```

#### `->has(string $key): bool`
```php
if ($request->has('email')) { /* ... */ }
```

#### `->json(?string $key = null, mixed $default = null): ?array`
JSON-decoded body (or a single key).
```php
$payload = $request->json();
$first   = $request->json('items.0');
```

### Validation

#### `->validate(array $rules, array $messages = [], array $attributes = []): array`
Validate input. Throws `Zero\Lib\Validation\ValidationException` on failure (handled by the global error handler).
```php
$data = $request->validate([
    'email'    => 'required|email',
    'password' => 'required|min:8',
]);
```

### Headers

#### `->header(?string $key = null, mixed $default = null): mixed`
Pass `null` to get the full header bag.
```php
$ua = $request->header('User-Agent');
$all = $request->header(); // ['user-agent' => '...', ...]
```

#### `->expectsJson(): bool`
True when the client `Accept`s JSON.
```php
return $request->expectsJson()
    ? Response::json($data)
    : view('users.show', ['user' => $data]);
```

#### `->wantsJson(): bool`
Stricter alias of `expectsJson()` ignoring `*/*`.
```php
if ($request->wantsJson()) { /* ... */ }
```

### Cookies

#### `->cookie(string $key, mixed $default = null): mixed`
```php
$theme = $request->cookie('theme', 'light');
```

#### `->cookies(): array`
```php
$all = $request->cookies();
```

### Files

#### `->file(string $key, mixed $default = null): mixed`
Returns an `UploadedFile` (or `null`).
```php
$avatar = $request->file('avatar');
$path   = $avatar?->store('avatars');
```

#### `->files(): array`
```php
$uploads = $request->files();
```

### Server / metadata

#### `->method(): string`
```php
$method = $request->method(); // 'POST'
```

#### `->path(): string`
```php
$request->path(); // '/users/42'
```

#### `->uri(): string`
Path + query string.
```php
$request->uri(); // '/users/42?tab=info'
```

#### `->root(): string`
Scheme + host (no path).
```php
$request->root(); // 'https://api.example.com'
```

#### `->fullUrl(): string`
```php
$request->fullUrl(); // 'https://api.example.com/users/42?tab=info'
```

#### `->ip(): ?string`
```php
$ip = $request->ip();
```

#### `->getContent(): string`
Raw request body.
```php
$raw = $request->getContent();
```

### Attributes (middleware ↔ controller)

Attributes let middleware stash work for downstream code. Backed by static state on `Request`.

#### `Request::set(string $key, mixed $value): void`
```php
// In middleware
Request::set('user', Auth::user());
```

#### `Request::get(string $key, mixed $default = null): mixed`
```php
// In a controller
$user = Request::get('user');
```

#### `->attribute(string $key, mixed $default = null): mixed`
Instance-level read.
```php
$user = $request->attribute('user');
```

#### `->attributes(): array`
```php
$bag = $request->attributes();
```

### Session

#### `->session(?string $key = null, mixed $default = null): mixed`
Read the session via the request.
```php
$flash = $request->session('flash.success');
$all   = $request->session(); // raw $_SESSION
```

### Property access

`$request->key` and `isset($request->key)` proxy to attributes/input — convenient inside controllers:
```php
$email = $request->email; // sugar for $request->input('email')
```

---

## Response

Static factories build a `Response`, which the router sends. You can also return raw scalars/arrays/models from controllers — `Response::resolve()` wraps them.

### Builders

#### `Response::make(mixed $content = '', int $status = 200, array $headers = []): static`
Generic constructor.
```php
return Response::make('OK', 200, ['X-Robots-Tag' => 'noindex']);
```

#### `Response::json(mixed $data, int $status = 200, array $headers = []): static`
```php
return Response::json(['ok' => true]);
return Response::json($users, 200, ['X-Total' => count($users)]);
```

#### `Response::text(string $text, int $status = 200, array $headers = []): static`
```php
return Response::text('pong');
```

#### `Response::html(string $html, int $status = 200, array $headers = []): static`
```php
return Response::html('<h1>Hello</h1>');
```

#### `Response::xml(string $xml, int $status = 200, array $headers = []): static`
```php
return Response::xml('<?xml version="1.0"?><root/>');
```

#### `Response::api(string $status, mixed $payload = null, int $statusCode = 200, array $headers = []): static`
Opinionated `{status, message, data}` envelope.
```php
return Response::api('success', $user);
return Response::api('error', null, 422, ['X-Error' => 'validation']);
```

### Redirects

#### `Response::redirect(string $location, int $status = 302, array $headers = []): static`
```php
return Response::redirect('/login');
```

#### `Response::redirectRoute(string $name, array $parameters = [], bool $absolute = true, int $status = 302, array $headers = []): static`
```php
return Response::redirectRoute('users.show', ['id' => $user->id]);
```

#### `Response::redirectBack(string $fallback = '/', int $status = 302, array $headers = []): static`
```php
return Response::redirectBack('/dashboard');
```

### Streams & files

#### `Response::stream(callable|string $stream, int $status = 200, array $headers = [], string $contentType = 'text/event-stream'): static`
```php
return Response::stream(function () {
    foreach (stream_events() as $event) {
        echo "data: " . json_encode($event) . "\n\n";
        ob_flush(); flush();
    }
});
```

#### `Response::file(string $path, array $headers = [], ?string $name = null, string $disposition = 'inline'): static`
```php
return Response::file(storage_path('exports/users.csv'), [], 'users.csv', 'attachment');
```

### Empty / pass-through

#### `Response::noContent(int $status = 204, array $headers = []): static`
```php
return Response::noContent();
```

#### `Response::resolve(mixed $value): static`
Normalize any controller return value to a `Response`. Used by the router.
```php
$response = Response::resolve($controllerReturn);
```

### Mutators

These chain on a Response instance:

#### `->status(int $status): self`
```php
return Response::json($data)->status(201);
```

#### `->withHeaders(array $headers): self`
```php
return Response::json($data)->withHeaders(['X-Total' => 42]);
```

#### `->getStatus(): int`
```php
$response->getStatus(); // 200
```

#### `->send(): void`
Emit headers + body. The router calls this for you.
```php
$response->send();
```

---

## Global helpers

These are thin wrappers around `Response::*` and `Request::*` defined in [`core/libraries/Support/Helper.php`](../core/libraries/Support/Helper.php). See [helpers.md](helpers.md) for the full list.

```php
return view('users.show', ['user' => $user]);     // Response::html
return response($payload, 201);                    // auto-detects type
return redirect('/login');                         // Response::redirect
return back();                                     // Response::redirectBack

$email = request('email');                         // Request::get
$user  = auth();                                   // current user
```
