# SOAP Client

`Http::soap()` returns a fluent SOAP request builder. Built on `ext-soap` when loaded; falls back to a minimal cURL-based envelope sender when it isn't.

```php
use Zero\Lib\Http;

// WSDL mode
$r = Http::soap('https://api.example.com/service?wsdl')
    ->withBasicAuth($user, $pass)
    ->call('GetRates', ['currency' => 'USD']);

// Non-WSDL mode
$r = Http::soap()
    ->endpoint('https://example.com/endpoint')
    ->uri('urn:example:service')
    ->action('urn:example:service#GetRates')
    ->call('GetRates', ['currency' => 'USD']);

// Magic method shortcut
$user = Http::soap()->endpoint($url)->uri($ns)->GetUser([42])->result();
```

Implementation: [`SoapRequest.php`](../../core/libraries/Http/SoapRequest.php) and [`SoapResponse.php`](../../core/libraries/Http/SoapResponse.php).

---

## Building the request

### `Http::soap(?string $wsdl = null): SoapRequest`
Entry point. Pass a WSDL URL/path, or omit and use `endpoint()` + `uri()`.

### `->wsdl(string $url): self`
```php
Http::soap()->wsdl('https://example.com/svc?wsdl')->call('Foo');
```

### `->endpoint(string $url): self`
```php
Http::soap()->endpoint('https://example.com/svc')->uri('urn:foo')->call('Bar');
```

### `->uri(string $namespace): self`
Target namespace for non-WSDL mode.
```php
Http::soap()->endpoint($url)->uri('urn:example:service');
```

### `->noWsdl(): self`
Force non-WSDL mode (drops any previously set WSDL).

### `->action(string $soapAction): self`
Sets the `SOAPAction` HTTP header.
```php
Http::soap()->endpoint($url)->uri($ns)->action('urn:Foo#Bar')->call('Bar');
```

### `->version(int $version): self`
`SOAP_1_1` (default) or `SOAP_1_2`.
```php
Http::soap()->endpoint($url)->uri($ns)->version(SOAP_1_2)->call('Foo');
```

### `->style(int $style): self`
`SOAP_RPC` or `SOAP_DOCUMENT`.

### `->use(int $use): self`
`SOAP_LITERAL` or `SOAP_ENCODED`.

### `->encoding(string $encoding): self`
```php
Http::soap()->encoding('UTF-8')->call('Foo');
```

### `->timeout(int $seconds): self`
Connection timeout.
```php
Http::soap('...')->timeout(10)->call('Foo');
```

### `->withWsdlCache(int $mode): self`
`WSDL_CACHE_NONE`, `WSDL_CACHE_DISK`, `WSDL_CACHE_MEMORY`, `WSDL_CACHE_BOTH`.
```php
Http::soap('...')->withWsdlCache(WSDL_CACHE_DISK);
```

### `->withBasicAuth(string $username, string $password): self`
```php
Http::soap('...')->withBasicAuth('user', 'pass')->call('Foo');
```

### `->withDigestAuth(string $username, string $password): self`
```php
Http::soap('...')->withDigestAuth('user', 'pass');
```

### `->withClientCertificate(string $path, ?string $passphrase = null): self`
```php
Http::soap('...')->withClientCertificate('/etc/ssl/client.pem', 'secret');
```

### `->withProxy(string $host, int $port, ?string $login = null, ?string $password = null): self`
```php
Http::soap('...')->withProxy('proxy.local', 3128);
```

### `->withUserAgent(string $userAgent): self`
```php
Http::soap('...')->withUserAgent('ZeroSoap/1.0');
```

### `->compression(int $flags): self`
`SOAP_COMPRESSION_*`.
```php
Http::soap('...')->compression(SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP);
```

### `->keepAlive(bool $keepAlive = true): self`
```php
Http::soap('...')->keepAlive();
```

### `->withClassMap(array $classMap): self`
Map WSDL types to PHP classes.
```php
Http::soap('...')->withClassMap(['User' => App\Soap\User::class]);
```

### `->withTypeMap(array $typeMap): self`
Custom (de)serialization hooks for specific XSD types.

### `->withFeatures(int $flags): self`
`SOAP_*` feature flags.

### `->withStreamContext($ctx): self`
Custom stream context.

### `->withOptions(array $options): self`
Pass any other `SoapClient` option directly.

### `->withSoapHeader(string $name, string $namespace, mixed $data = null, bool $mustUnderstand = false, ?string $actor = null): self`
Add a SOAP header.
```php
Http::soap('...')
    ->withSoapHeader('Auth', 'urn:zero', ['token' => $tok])
    ->call('Foo');
```

### `->trace(bool $trace = true): self`
Capture last request/response on the response object.
```php
$r = Http::soap('...')->trace()->call('Foo');
$r->lastRequest();
```

### `->throw(bool $throw = true): self`
Throw `SoapFault` on failure.
```php
Http::soap('...')->throw()->call('Foo'); // throws on fault
```

### `->withClient(string $class): self`
Use a custom `SoapClient` subclass (must extend `SoapClient`).
```php
Http::soap('...')->withClient(MyClient::class);
```

---

## Calling

### `->call(string $method, array $arguments = []): SoapResponse`
```php
$r = Http::soap('...')->call('Add', [2, 3]);
$r->result(); // 5
```

### Magic call: `->$method($args)`
```php
$r = Http::soap()->endpoint($url)->uri($ns)->Add([10, 20]);
$r->result(); // 30
```

### Introspection (requires `ext-soap` + WSDL)

#### `->client(): SoapClient`
```php
$client = Http::soap('...')->client();
```

#### `->functions(): array<int, string>`
```php
Http::soap('...')->functions(); // ['Add(int $a, int $b) Add', ...]
```

#### `->types(): array<int, string>`
```php
Http::soap('...')->types();     // ['struct User { ... }', ...]
```

---

## Inspecting the response

Implementation: [`SoapResponse.php`](../../core/libraries/Http/SoapResponse.php).

### `result(): mixed`
The decoded service result.
```php
Http::soap('...')->call('Add', [2, 3])->result(); // 5
```

### `get(?string $key = null, $default = null): mixed`
Dot-notation lookup (works on arrays and objects).
```php
$r = Http::soap('...')->call('GetUser', [42]);
$r->get('id');         // 42
$r->get('meta.level'); // 7
```

### `toArray(): array`
Recursively normalize objects to arrays.
```php
Http::soap('...')->call('GetUser', [42])->toArray();
```

### `headers(): array`
SOAP response headers.

### `fault(): ?SoapFault` / `successful(): bool` / `failed(): bool`
```php
$r = Http::soap('...')->call('Boom');
if ($r->failed()) {
    $fault = $r->fault();
}
```

### `lastRequest(): ?string` / `lastRequestHeaders(): ?string`
Available when `trace()` was set.

### `lastResponse(): ?string` / `lastResponseHeaders(): ?string`
Available when `trace()` was set.

### `throw(): self`
Throw the fault (if any), else return `$this`.
```php
Http::soap('...')->call('Foo')->throw();
```
