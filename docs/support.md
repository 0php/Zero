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

