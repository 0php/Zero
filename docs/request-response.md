# Request & Response Lifecycle

Zero Framework models each HTTP cycle with dedicated request and response abstractions that mimic Laravel's ergonomics while staying dependency free.

## Request Capture (`Zero\Lib\Http\Request`)

- The router calls `Request::capture()` once per request, snapshotting `$_GET`, `$_POST`, `$_FILES`, `$_COOKIE`, and `$_SERVER`.
- The raw body (`php://input`) is buffered, enabling JSON decoding through `Request::json()`.
- Common helpers:
  - `Request::input('user.email')` – dot-notation lookup into merged query, form, and JSON payloads.
- `Request::query()` / `Request::post()` – direct access to GET/POST data.
- `Request::file('avatar')` – returns an `UploadedFile` wrapper for the uploaded payload.
  - `Request::header('accept')` – case-insensitive header retrieval.
  - `Request::expectsJson()` / `Request::wantsJson()` – drives content negotiation.
  - `Request::ip()` – best-effort client IP detection.
- The singleton instance is accessible via `Request::instance()` and injected automatically when a controller or middleware type-hints `Zero\Lib\Http\Request`.

### Uploaded Files

- Retrieve uploads via `Request::file('avatar')`; the method returns an `UploadedFile` instance with helpers like `getClientOriginalName()` and `store()`.
- Multiple uploads (e.g., `photos[]`) return an array of `UploadedFile` objects that can each be stored individually.
- Call `$file->store('avatars')` to persist the file using the storage layer, or `$file->storeAs('avatars', 'custom-name.jpg')` for custom filenames.

### Request Attributes

- Middleware can prime downstream layers with `Request::set('current_user', $user)` (attributes accept any value: models, DTOs, tokens, etc.).
- Controllers or views read stored data via `Request::get('current_user')`, `Request::attribute('current_user')`, or property access on the live instance (`Request::instance()->current_user`).
- Attributes live for the lifetime of the current HTTP cycle. Each new request receives a fresh request instance, so nothing leaks between users.


### Validating Input

```php
$data = Request::validate([
    'name' => ['required', 'string', 'min:3'],
    'email' => ['required', 'email', 'unique:users,email'],
    'role_id' => ['exists:roles,id'],
]);

User::create($data);
```

- Rules accept pipe strings (`'required|min:3'`), arrays of rule strings, rule objects, or closures.
- Validation failures raise `Zero\Lib\Validation\ValidationException`. HTTP requests receive a 422 response (JSON includes an `errors` bag; HTML fallback renders a formatted list). CLI contexts print the messages to `STDERR`.
- Built-in rules cover the common cases; extend the system by implementing `Zero\Lib\Validation\RuleInterface` or passing a closure.

| Rule | Description |
| --- | --- |
| `required` | Field must be present and non-empty (arrays must contain at least one element). |
| `string` | Value must be a string when provided. |
| `email` | Validates format using `FILTER_VALIDATE_EMAIL`. |
| `boolean` | Accepts booleans, `0`/`1`, or string equivalents (`"true"`, `"false"`, `"on"`, `"off"`). |
| `array` | Requires the value to be an array. |
| `min:<value>` | Strings: minimum length; arrays: minimum item count; numerics: minimum numeric value. |
| `max:<value>` | Strings: maximum length; arrays: maximum item count; numerics: maximum numeric value. |
| `confirmed` | Requires a matching `<field>_confirmation` input. |
| `exists:table,column` | Ensures the value (or each value in an array) exists in the specified table/column (column defaults to the attribute name). |
| `unique:table,column,ignore,idColumn` | Fails when the value already exists; optional ignore/id parameters match Laravel's signature. Arrays are checked element by element. |
| `password:letters,numbers,symbols` | Enforces password character classes; pass comma-separated requirements (omit parameters for a simple string check). |
- Override error copy or attribute names by supplying the optional `$messages` / `$attributes` arrays:

```php
$credentials = Request::validate(
    ['password' => ['required', 'string', 'min:8', 'password:letters,numbers', 'confirmed']],
    ['password.min' => 'Passwords must contain at least :min characters.'],
    ['password' => 'account password']
);
```

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
