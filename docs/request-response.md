# Request & Response Lifecycle

Zero Framework models each HTTP cycle with dedicated request and response abstractions that mimic Laravel's ergonomics while staying dependency free.

## Request Capture (`Zero\Lib\Http\Request`)

- The router calls `Request::capture()` once per request, snapshotting `$_GET`, `$_POST`, `$_FILES`, `$_COOKIE`, and `$_SERVER`.
- The raw body (`php://input`) is buffered, enabling JSON decoding through `Request::json()`.
- Common helpers:
  - `Request::input('user.email')` – dot-notation lookup into merged query, form, and JSON payloads.
  - `Request::query()` / `Request::post()` – direct access to GET/POST data.
  - `Request::header('accept')` – case-insensitive header retrieval.
  - `Request::expectsJson()` / `Request::wantsJson()` – drives content negotiation.
  - `Request::ip()` – best-effort client IP detection.
- The singleton instance is accessible via `Request::instance()` and injected automatically when a controller or middleware type-hints `Zero\Lib\Http\Request`.

## Response Building (`Zero\Lib\Http\Response`)

Controllers can return a wide range of values; the router normalises them with `Response::resolve()`:

| Controller Return Value | Normalised Response |
| --- | --- |
| `Http\Response` instance | Returned as-is |
| `null` | `Response::noContent()` |
| `array`, `JsonSerializable`, `Traversable`, generic object | `Response::json()` |
| `Throwable` | JSON error payload with status `500` |
| Scalar / stringable object | `Response::html()` |

### Factory Helpers

- `Response::make($html, $status)` – base factory with default headers.
- `Response::json($data, $status)` – structured JSON with UTF-8 headers.
- `Response::text($string)` / `Response::html($markup)` / `Response::xml($xml)`
- `Response::api($statusLabel, $payload, $statusCode)` – opinionated API envelope.
- `Response::redirect($location, $status)` – sets the `Location` header.
- `Response::stream($callbackOrString)` – SSE or streaming responses.

Global helper functions `response($value, $status = 200, $headers = [])` and `view($template, $data, $status)` wrap these factories so controllers and services can normalise return payloads consistently.

`Response::send()` writes headers, status code, and either echoes buffered content or streams via the provided handler. The HTTP bootstrap expects controllers to return an `Http\Response` instance and falls back to echoing strings for compatibility.

## Middleware Short-Circuiting

Route middlewares can return any of the supported response values. If a middleware returns a non-null value, the router resolves it into a `Response` and halts further processing, enabling authentication/authorization checks to block requests gracefully.

## Content Negotiation Example

```php
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;
use Zero\Lib\View;

class UsersController
{
    public function index(Request $request)
    {
        $users = DBML::table('users')->orderBy('name')->get();

        if ($request->expectsJson()) {
            return ['data' => $users];
        }

        return Response::html(View::render('users/index', compact('users')));
    }
}
```
