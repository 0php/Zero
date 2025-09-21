# Support Utilities

Zero ships a couple of lightweight helpers that make day-to-day tasks easier. The HTTP client saves you from wrestling with raw cURL handles, and the string helper mirrors Laravel's `Str` facade for quick transformations.

## HTTP Client

Namespace: `Zero\Lib\Http\Http`

The HTTP client wraps PHP's cURL extension with a fluent API and sensible defaults.

### Quick start

```php
use Zero\Lib\Http\Http;

$response = Http::timeout(10)
    ->acceptJson()
    ->get('https://api.example.com/posts', ['page' => 2]);

if ($response->successful()) {
    $payload = $response->json();
}
```

### Common patterns

- `Http::get($url, $query = [])`
- `Http::post($url, $data = [])`
- `Http::withHeaders([...])->post(...)`
- `Http::attach($name, $contents, $filename)` for multipart uploads
- `Http::asJson()` to send JSON bodies (automatically sets headers)
- `Http::retry($times, $sleepMs)` for simple retry policies
- Use `$response->status()`, `$response->body()`, `$response->json()`, `$response->headers()` to inspect responses.

By default requests throw no exceptions—check `$response->failed()` or `$response->successful()` as needed.

## String Helpers

Namespace: `Zero\Lib\Support\Str`

`Str` mirrors Laravel's helper with a static API.

```php
use Zero\Lib\Support\Str;

Str::studly('make_http_client');    // MakeHttpClient
Str::camel('make_http_client');     // makeHttpClient
Str::snake('MakeHTTPClient');       // make_http_client
Str::slug('Hello World!');          // hello-world
Str::limit('A very long sentence', 10); // A very...
```

Available helpers include:

- `studly`, `camel`, `snake`, `kebab`, `slug`, `title`
- `upper`, `lower`
- `contains`, `startsWith`, `endsWith`
- `limit`

These helpers are framework-agnostic and usable in both CLI and HTTP code paths.

## Storage

Namespace: `Zero\\Lib\\Storage\\Storage`

The storage facade provides a thin wrapper around the configured filesystem disks. The default `local` driver writes to `storage/` and can be customised through `config/storage.php` or `.env` (`STORAGE_DISK`, `STORAGE_LOCAL_ROOT`).

### Writing files

```php
use Zero\\Lib\\Storage\\Storage;

$path = Storage::put('reports/latest.txt', "Report generated at " . date('c'));
// $path === 'reports/latest.txt'

// Read it back directly from the disk root
$contents = file_get_contents(storage_path($path));
```

Pair it with uploaded files:

```php
$avatar = Request::file('avatar');

if ($avatar && $avatar->isValid()) {
    $path = $avatar->store('avatars');
    // $path => avatars/slug-64d2c6b1e5c3.jpg
}
```

Need a custom name?

```php
$avatar->storeAs('avatars', 'user-' . $user->id . '.jpg');
```

Additional disks can be registered in `config/storage.php`; the storage manager will throw if you request a disk that has not been configured.

Create filesystem links with the CLI:

```bash
php zero storage:link
```

The command reads the `links` array in `config/storage.php` (defaults to linking `public/storage` to the `public` disk) and creates the appropriate symlinks. Ensure the web server user has permission to create the link path or run the command with suitable privileges.
