# Support Utilities

Zero ships a couple of lightweight helpers that make day-to-day tasks easier. The HTTP client saves you from wrestling with raw cURL handles, the `DateTime` helper wraps immutable dates with convenience methods, and the string helper mirrors Laravel's `Str` facade for quick transformations.

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

## Date & Time

Namespace: `Zero\Lib\Support\DateTime`

The support `DateTime` class extends PHP's `DateTimeImmutable` with fluent helpers for common tasks.

```php
use Zero\Lib\Support\DateTime;

$now = DateTime::now();
$tomorrow = $now->addDays(1);

echo $tomorrow->diffForHumans($now); // 1 day from now

$parisNoon = DateTime::parse('today 12:00', new DateTimeZone('Europe/Paris'));
$local = $parisNoon->inTimeZone('UTC');
```

Companion facade `Zero\Lib\Support\Date` keeps the old mutable wrapper for backwards-compatible chaining, but new code should prefer the immutable `DateTime` helper.

Useful methods:

- `DateTime::now(?DateTimeZone $tz = null)` – immutable current timestamp
- `DateTime::parse(string $value, ?DateTimeZone $tz = null)` – parse relative or absolute strings
- `addDays(int $days)` / `subDays(int $days)` – clone with days added or subtracted
- `diffForHumans(DateTimeInterface $other)` – human readable difference (`"2 hours ago"`, `"3 days from now"`)
- `inTimeZone(string|DateTimeZone $tz)` – convert to a new timezone

## Filesystem Helpers

Namespace: `Zero\Lib\Filesystem\File`

The `File` class wraps filesystem operations (reading, writing, hashing, MIME helpers) and now supports in-memory factories:

- `File::fromPath('/path/to/file')`
- `File::fromContents($string, extension: 'txt')`
- `File::fromBase64($payload, extension: 'png')`
- `File::fromUrl('https://example.com/logo.png')`
- `File::from($mixed)` – auto-detects path, URL, base64 or raw contents

Every file exposes helpers such as `getSignedUrl()`, `setMimeType()`, `setExtension()`, `isImage()/isVideo()/is('pdf')`, etc. These instances integrate with `Storage::put()`—you can pass a `File` (or `UploadedFile`, which extends it) directly to persist it on any disk.

## String Helpers

Namespace: `Zero\Lib\Support\Str`

`Str` mirrors Laravel's helper with a static API and adds a fluent wrapper for chained transformations.

```php
use Zero\Lib\Support\Str;

Str::studly('make_http_client');    // MakeHttpClient
Str::camel('make_http_client');     // makeHttpClient
Str::snake('MakeHTTPClient');       // make_http_client
Str::slug('Hello World!');          // hello-world
Str::limit('A very long sentence', 10); // A very...
Str::random(32);                    // e.g. 3kt1h9...
Str::after('auth:token', ':');      // token
Str::containsAll('queue:email', ['queue', 'email']); // true

Str::of('users.profile-photo')
    ->replaceLast('.', '/')
    ->slug('/');                    // users/profile-photo
```

### Helper reference

#### Transformations

| Method | Purpose | Example |
| --- | --- | --- |
| `Str::studly('make_http_client')` | Convert to StudlyCase | `MakeHttpClient` |
| `Str::camel('make_http_client')` | Convert to camelCase | `makeHttpClient` |
| `Str::snake('MakeHTTPClient')` | Convert to snake_case | `make_http_client` |
| `Str::kebab('MakeHTTPClient')` | Convert to kebab-case | `make-http-client` |
| `Str::slug('Héllø Wørld')` | URL-friendly slug with ASCII transliteration | `hello-world` |
| `Str::title('make http client')` | Title case words | `Make Http Client` |
| `Str::upper('Zero')` | Uppercase string | `ZERO` |
| `Str::lower('Zero')` | Lowercase string | `zero` |
| `Str::ascii('déjà vu')` | Transliterate to ASCII | `deja vu` |

#### Search helpers

| Method | Purpose | Example |
| --- | --- | --- |
| `Str::contains('queue:email', 'email')` | Check for substring | `true` |
| `Str::containsAll('queue:email', ['queue', 'email'])` | Ensure all needles are present | `true` |
| `Str::containsAny('queue:email', ['http', 'email'])` | Ensure any needles are present | `true` |
| `Str::startsWith('cache:foo', 'cache:')` | Check prefix | `true` |
| `Str::startsWithAny('cache:foo', ['queue:', 'cache:'])` | Check multiple prefixes | `true` |
| `Str::endsWith('image.png', '.png')` | Check suffix | `true` |
| `Str::endsWithAny('image.backup.tar.gz', ['.zip', '.tar.gz'])` | Check multiple suffixes | `true` |

#### Extraction helpers

| Method | Purpose | Example |
| --- | --- | --- |
| `Str::limit('A very long sentence', 10)` | Trim by characters | `A very...` |
| `Str::limitWords('One two three four', 3)` | Trim by words | `One two three...` |
| `Str::words('One two three four', 2)` | Take N words | `One two...` |
| `Str::substr('framework', 0, 5)` | Multibyte-aware substring | `frame` |
| `Str::after('auth:token', ':')` | Portion after first occurrence | `token` |
| `Str::before('auth:token', ':')` | Portion before first occurrence | `auth` |
| `Str::between('[42]', '[', ']')` | Portion between two markers | `42` |

#### Replacement & formatting

| Method | Purpose | Example |
| --- | --- | --- |
| `Str::replaceFirst('zero', 'one', 'zero zero')` | Replace first occurrence | `one zero` |
| `Str::replaceLast('zero', 'one', 'zero zero')` | Replace last occurrence | `zero one` |
| `Str::swap(['{name}' => 'Zero'], 'Hello {name}')` | Replace using a map | `Hello Zero` |
| `Str::ensureSuffix('storage/logs', '/')` | Append suffix if missing | `storage/logs/` |
| `Str::padLeft('7', 3, '0')` | Left pad to length | `007` |
| `Str::padRight('7', 3, '0')` | Right pad to length | `700` |
| `Str::padBoth('core', 8, '-')` | Pad evenly on both sides | `--core--` |
| `Str::repeat('-', 5)` | Repeat string | `-----` |

#### Length & randomness

| Method | Purpose | Example |
| --- | --- | --- |
| `Str::length('ありがとう')` | Multibyte-safe length | `5` |
| `Str::uuid()` | RFC4122 random UUID (v4) | `d3b0...` |
| `Str::random(16)` | Random token from base62 alphabet | `k9Qz...` |

#### Fluent chains

| Method | Purpose | Example |
| --- | --- | --- |
| `Str::of('users.profile-photo')` | Start fluent chain with `Stringable` | `Stringable` instance |

The fluent wrapper keeps string results chainable and lets you tap into non-string returns when needed:

```php
use Zero\Lib\Support\Str;
use Zero\Lib\Support\Stringable as FluentString;

$profilePath = Str::of('users.profile-photo')
    ->replaceLast('.', '/')
    ->slug('/'); // users/profile-photo

$queueName = Str::of('queue')
    ->ensureSuffix(':default')
    ->pipe(fn (FluentString $stringable) => (string) $stringable); // queue:default
```

These helpers are framework-agnostic and usable in both CLI and HTTP code paths. See `Zero\Lib\Support\Stringable` for the fluent helper implementation and available chaining behaviour.
