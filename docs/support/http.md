# HTTP Client

Namespace: `Zero\Lib\Http` (the `Http` facade), `Zero\Lib\Http\PendingRequest`, `Zero\Lib\Http\ClientResponse`.

The HTTP client wraps PHP's cURL extension. There are two ways to use it:

- **Fluent** (preferred) — returns a `ClientResponse`
- **Legacy static** (back-compatible) — returns a plain `stdClass` with `ok`, `status`, `body`, `json`, `error`

```php
use Zero\Lib\Http;

// Fluent
$r = Http::timeout(10)->acceptJson()->get('https://api.example.com/posts');
if ($r->successful()) {
    $posts = $r->json('data', []);
}

// Legacy
$r = Http::get($url, $query, $headers, $timeout);
if ($r->ok) {
    $data = $r->json;
}
```

Requests don't throw by default — call `$r->throw()` (or `->throw(true)` on the pending request) to opt in.

---

## Setting up the request

Implementation: [`PendingRequest.php`](../../core/libraries/Http/PendingRequest.php). Every fluent setter on `Http::*` returns a `PendingRequest`. Terminal verbs (`get`/`post`/`put`/`patch`/`delete`/`head`/`send`) execute and return a [`ClientResponse`](#inspecting-the-response).

### `Http::timeout(int $seconds): PendingRequest`
Total request timeout (also applies to connect when `connectTimeout` not set).
```php
Http::timeout(5)->get($url);
```

### `Http::connectTimeout(int $seconds): PendingRequest`
```php
Http::connectTimeout(2)->timeout(10)->get($url);
```

### `Http::withHeaders(array $headers): PendingRequest`
```php
Http::withHeaders(['X-A' => '1', 'X-B' => '2'])->get($url);
```

### `Http::withHeader(string $name, string $value): PendingRequest`
```php
Http::withHeader('X-Trace', $traceId)->get($url);
```

### `Http::acceptJson(): PendingRequest`
```php
Http::acceptJson()->get($url);
```

### `Http::accept(string $contentType): PendingRequest`
```php
Http::accept('application/xml')->get($url);
```

### `Http::asJson(): PendingRequest`
JSON-encode the body (default).
```php
Http::asJson()->post($url, ['k' => 'v']);
```

### `Http::asForm(): PendingRequest`
URL-encoded body.
```php
Http::asForm()->post($url, ['email' => $email]);
```

### `Http::asMultipart(): PendingRequest`
Multipart body — usually pair with `attach()`.
```php
Http::asMultipart()->attach('file', '/tmp/x.png')->post($url);
```

### `Http::bodyFormat(string $format): PendingRequest`
Low-level switch: `json`, `form`, `multipart`, or `body` (raw string passthrough).
```php
Http::bodyFormat('body')->contentType('text/xml')->post($url, $rawXml);
```

### `Http::contentType(string $type): PendingRequest`
```php
Http::contentType('application/xml')->post($url, $xml);
```

### `Http::withToken(string $token, string $type = 'Bearer'): PendingRequest`
```php
Http::withToken($accessToken)->get($url);
```

### `Http::withBasicAuth(string $username, string $password): PendingRequest`
```php
Http::withBasicAuth('user', 'pass')->get($url);
```

### `Http::withQueryParameters(array $query): PendingRequest`
Merged with any query passed to the verb.
```php
Http::withQueryParameters(['page' => 2])->get($url);
```

### `Http::withCookies(array $cookies): PendingRequest`
```php
Http::withCookies(['session' => $sid, 'theme' => 'dark'])->get($url);
```

### `Http::withUserAgent(string $userAgent): PendingRequest`
```php
Http::withUserAgent('Zero/1.0')->get($url);
```

### `Http::withoutVerifying(): PendingRequest`
Disable TLS peer/host verification (development only).
```php
Http::withoutVerifying()->get('https://self-signed.test');
```

### `Http::baseUrl(string $url): PendingRequest`
Prefix relative URLs.
```php
Http::baseUrl('https://api.example.com')->get('/users'); // https://api.example.com/users
```

### `Http::withOptions(array $options): PendingRequest`
Raw curl option overrides.
```php
Http::withOptions([CURLOPT_FOLLOWLOCATION => false])->get($url);
```

### `Http::attach(string $name, mixed $contents, ?string $filename = null, array $headers = []): PendingRequest`
Multipart upload. `$contents` may be a path on disk or an in-memory string.
```php
Http::attach('avatar', '/tmp/me.png', 'avatar.png')->post($url);
Http::attach('payload', $bytes, 'payload.bin')->post($url);
```

### `Http::retry(int $times, int $sleepMs = 0, ?callable $when = null): PendingRequest`
Auto-retry on failure. Optional `$when` callback receives `($response, $attempt)`.
```php
Http::retry(3, 200)->get($url);
Http::retry(3, 200, fn ($r, $i) => $r->serverError())->get($url);
```

### `Http::dump(): PendingRequest` / `Http::dd(): PendingRequest`
Debug the outgoing request (then continue / die).
```php
Http::dump()->withToken($t)->get($url);
```

### Default headers (process-wide)

```php
Http::setDefaultHeaders(['X-Client' => 'zero/1.0']);
Http::addDefaultHeader('X-Trace', $traceId);
```
These persist for both legacy and fluent calls in the same process.

---

## Verb methods

All return a `ClientResponse` when called fluently.

### `->get(string $url, array $query = []): ClientResponse`
```php
Http::acceptJson()->get('https://api.example.com/users', ['page' => 2]);
```

### `->head(string $url, array $query = []): ClientResponse`
Returns headers only (no body).
```php
Http::acceptJson()->head($url);
```

### `->post(string $url, mixed $data = null, array $query = []): ClientResponse`
```php
Http::asJson()->post($url, ['email' => $email]);
```

### `->put(string $url, mixed $data = null, array $query = []): ClientResponse`
```php
Http::asJson()->put("$url/$id", $payload);
```

### `->patch(string $url, mixed $data = null, array $query = []): ClientResponse`
```php
Http::asJson()->patch("$url/$id", ['status' => 'active']);
```

### `->delete(string $url, mixed $data = null, array $query = []): ClientResponse`
```php
Http::asJson()->delete("$url/$id");
```

### `->send(string $method, string $url, mixed $data = null, array $query = []): ClientResponse`
Custom verb (or runtime-chosen).
```php
Http::asJson()->send('PURGE', $url);
```

---

## Inspecting the response

Implementation: [`ClientResponse.php`](../../core/libraries/Http/ClientResponse.php).

### `status(): int`
```php
Http::get($url)->status(); // 200
```

### `body(): string`
```php
$body = Http::get($url)->body();
```

### `json(?string $key = null, $default = null): mixed`
Decode the JSON body. Dot-notation supported.
```php
$r = Http::asJson()->get($url);
$r->json();                       // full decoded array
$r->json('data.user.id');         // dot-path lookup
$r->json('missing', 'fallback');  // default
```

### `object(): ?object`
JSON decoded as `stdClass`.
```php
Http::asJson()->get($url)->object()?->user?->id;
```

### `headers(): array` / `header(string $name): ?string`
```php
$r = Http::get($url);
$r->headers();             // ['Content-Type' => ['application/json'], ...]
$r->header('Content-Type'); // 'application/json'
```

### Status checks
```php
$r = Http::get($url);
$r->ok();             // status === 200
$r->successful();     // 200..299
$r->redirect();       // 300..399
$r->clientError();    // 400..499
$r->serverError();    // 500..599
$r->failed();         // connection error OR 4xx OR 5xx
$r->unauthorized();   // 401
$r->forbidden();      // 403
$r->notFound();       // 404
```

### `error(): ?string`
Underlying curl error, if any.
```php
Http::timeout(1)->get('http://10.255.255.1')->error(); // 'Operation timed out...'
```

### `throw(): self`
Throw `RuntimeException` if `failed()`; otherwise return `$this` for chaining.
```php
$r = Http::asJson()->get($url)->throw();
```

### `__toString()`
Casts to the response body.
```php
$body = (string) Http::get($url);
```

---

## Legacy static API

The original positional-argument API still works and returns a plain `stdClass`:

```php
$r = Http::get($url, $query, $headers, $timeout);
$r->ok;     // bool
$r->status; // int
$r->body;   // string|null
$r->json;   // decoded array or null
$r->error;  // string|null
```

Available on `Http::get`, `Http::post`, `Http::put`, `Http::patch`, `Http::delete`. Default headers from `Http::setDefaultHeaders()` apply to both APIs.
