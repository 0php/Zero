# Controllers

Controllers are the place where a route turns into work. In Zero a controller is just a class under `App\Controllers` whose public methods accept the `Request` and return a value the framework can turn into a `Response`. There's no inheritance, no IoC container, no service-provider boilerplate — the router resolves the class, calls the method, and lets you return anything sensible.

---

## Contents

- [Quick start](#quick-start)
- [Generating a controller](#generating-a-controller)
- [Method signature](#method-signature)
- [Returning responses](#returning-responses)
- [Reading the request](#reading-the-request)
- [Validation](#validation)
- [Route parameters](#route-parameters)
- [Authentication & authorization](#authentication--authorization)
- [Sessions, cookies, headers](#sessions-cookies-headers)
- [File uploads](#file-uploads)
- [Streaming & file downloads](#streaming--file-downloads)
- [Dispatching jobs from a controller](#dispatching-jobs-from-a-controller)
- [Resource controllers (REST)](#resource-controllers-rest)
- [Sub-namespaces and grouping](#sub-namespaces-and-grouping)
- [Testing controllers](#testing-controllers)
- [Common patterns](#common-patterns)
- [Tips](#tips)

---

## Quick start

```bash
php zero make:controller PostController
```

Creates `app/controllers/PostController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;

class PostController
{
    public function index(Request $request): Response
    {
        return Response::redirectRoute('home');
    }
}
```

Wire the route in `routes/web.php`:

```php
use App\Controllers\PostController;
use Zero\Lib\Router;

Router::get('/posts', [PostController::class, 'index'])->name('posts.index');
Router::post('/posts', [PostController::class, 'store'])->name('posts.store');
Router::get('/posts/{id}', [PostController::class, 'show'])->name('posts.show');
```

That's the whole loop. Each controller method is the entry point for one route.

---

## Generating a controller

### `php zero make:controller Name [--force]`

Creates `app/controllers/Name.php` under namespace `App\Controllers`. Use `/` to nest:

```bash
php zero make:controller Admin/UserController
# -> app/controllers/Admin/UserController.php (namespace App\Controllers\Admin)

php zero make:controller Api/V1/PostController
# -> app/controllers/Api/V1/PostController.php (namespace App\Controllers\Api\V1)
```

`--force` overwrites an existing file.

The generated stub uses `Request` and `Response` and returns a redirect, but you can replace it freely — controllers have no required base class.

---

## Method signature

A controller method receives:

1. **The `Request` instance** if you type-hint it in the first parameter
2. **Route parameters** (`{id}`, `{slug}`) as scalars in the order they appear in the route pattern
3. The return value — anything the router can turn into a `Response`

The simplest signatures:

```php
public function index(): Response                         { ... }
public function index(Request $request): Response         { ... }
public function show(string $id): Response                { ... }
public function show(Request $request, string $id): Response { ... }
```

Both `Request $request` and route parameters are optional — declare only what you need.

### Route parameters

Route parameters arrive as **strings** in the order they appear in the pattern. Type-hint them as `string`, `int`, etc.; PHP will coerce when possible:

```php
// route: Router::get('/posts/{id}', [PostController::class, 'show']);
public function show(int $id): Response
{
    $post = Post::find($id);
    return view('posts.show', compact('post'));
}

// route: Router::get('/posts/{year}/{slug}', [PostController::class, 'archive']);
public function archive(int $year, string $slug): Response
{
    // ...
}
```

If you also want the request, put it first:

```php
public function archive(Request $request, int $year, string $slug): Response
{
    // ...
}
```

Optional segments (`{lang?}`) arrive as `null`:

```php
// route: Router::get('/{lang?}/posts', [...]);
public function index(?string $lang = null): Response
{
    // ...
}
```

---

## Returning responses

Anything the router can stringify or JSON-encode is a valid return value. The `Response` helpers below cover the common cases.

### Plain values
```php
return 'Hello world';                       // text/html string
return ['ok' => true, 'count' => 42];       // -> JSON
return $user;                               // model -> JSON via JsonSerializable
```

### Views
```php
return view('pages.home');
return view('posts.show', ['post' => $post]);
return view('posts.show', compact('post'), 200, ['X-Cache' => 'MISS']);
```

### `Response::*` factories
```php
return Response::json(['data' => $rows], 200);
return Response::text('OK');
return Response::html('<h1>Hi</h1>');
return Response::noContent();                          // 204
return Response::redirect('/dashboard');
return Response::redirect('/login', 303);
return Response::redirectRoute('posts.show', ['id' => $post->id]);
return Response::redirectBack('/');                    // back to referer (or fallback)
return Response::file($absolutePath);                  // streamed inline
return Response::download($absolutePath, 'invoice.pdf');
```

### Global helpers
The same factories are available as global helpers, no `use` required:
```php
return view('home');
return response($payload, 201);
return redirect('/login');
return back();
return route('posts.show', ['id' => 42]);  // returns a string URL, not a Response
```

### Headers and status
```php
return Response::json($data)
    ->withHeaders(['X-Total' => 100])
    ->status(206);
```

---

## Reading the request

Type-hint `Zero\Lib\Http\Request` in the method signature. Inside, query/body/JSON/files are all addressable via the same input methods.

```php
public function store(Request $request): Response
{
    $email = $request->input('email');                  // any source (query, form, JSON)
    $page  = $request->input('page', 1);                // with default
    $hasName = $request->has('name');
    $all = $request->all();

    return response($all);
}
```

### Method matrix

| Method | Returns |
| --- | --- |
| `$request->input($key, $default = null)` | Single value from query / form / JSON, in that order |
| `$request->all()` | Merged input array |
| `$request->has($key)` | `true` if the key exists in any source |
| `$request->json($key = null, $default = null)` | The JSON body, or a single key from it |
| `$request->query($key = null, $default = null)` | Query-string only |
| `$request->method()` | `'GET'`, `'POST'`, etc. |
| `$request->path()` | Path component of the URL |
| `$request->uri()` | Path + query string |
| `$request->fullUrl()` | Absolute URL of the current request |
| `$request->ip()` | Best-effort client IP |
| `$request->expectsJson()` | `true` if `Accept: application/json` |
| `$request->wantsJson()` | True for `Accept: application/json` *or* `X-Requested-With: XMLHttpRequest` |
| `$request->header($name, $default)` | A header value (null when absent) |
| `$request->cookie($name, $default)` | A cookie value |
| `$request->session($key = null, $default = null)` | Read from the session |
| `$request->file($key)` | An `UploadedFile` instance (or null) |
| `$request->files()` | Every uploaded file keyed by field name |

`Request::instance()` returns the same instance from anywhere — no need to plumb it through if it's inconvenient.

```php
use Zero\Lib\Http\Request;

class HomeController
{
    public function index(): Response
    {
        $referer = Request::instance()->header('Referer');
        return view('pages.home', compact('referer'));
    }
}
```

### Reading raw JSON

For pure-API endpoints that always receive JSON:

```php
public function store(Request $request): Response
{
    $payload = $request->json();              // entire decoded body
    $title   = $request->json('title');       // single key
    $tags    = $request->json('tags', []);    // with default
    // ...
}
```

---

## Validation

`Request::validate()` returns the validated payload or throws `ValidationException`. The exception's `errors()` method returns a `field => [messages]` array, ready to flash into the session.

```php
use Zero\Lib\Validation\ValidationException;

public function store(Request $request): Response
{
    try {
        $data = $request->validate([
            'title'   => ['required', 'string', 'max:120'],
            'body'    => ['required', 'string'],
            'tags'    => ['nullable', 'array'],
            'tags.*'  => ['string', 'max:32'],
        ]);
    } catch (ValidationException $e) {
        Session::set('errors', array_map(fn ($msgs) => $msgs[0] ?? '', $e->errors()));
        Session::set('old', $request->all());
        return redirect()->back();
    }

    Post::create($data);

    return Response::redirectRoute('posts.index');
}
```

For JSON APIs the framework's exception handler will already render a 422 with the errors — you don't need to catch `ValidationException` yourself unless you want to flash and redirect for HTML form submissions.

```php
// API endpoint — let the handler render the 422
public function store(Request $request): Response
{
    $data = $request->validate([
        'email' => ['required', 'email'],
        'role'  => ['required', 'in:admin,member'],
    ]);

    $user = User::create($data);

    return Response::json($user, 201);
}
```

See [validation.md](support.md) for the full rule list.

---

## Route parameters

Route parameters are matched in order by name. Inside the controller they're regular function arguments — type-hint and default them like any PHP method:

```php
// Router::get('/posts/{id}', [PostController::class, 'show']);
public function show(int $id): Response
{
    $post = Post::findOrFail($id);
    return view('posts.show', compact('post'));
}

// Router::get('/users/{user}/posts/{post}', [PostController::class, 'edit']);
public function edit(int $user, int $post): Response
{
    // ...
}
```

There's no automatic model binding (yet) — call `Model::find($id)` explicitly.

If a route declares an optional segment, default the parameter to `null`:

```php
// Router::get('/{lang?}/dashboard', [DashboardController::class, 'index']);
public function index(?string $lang = null): Response
{
    if ($lang !== null) {
        set_locale($lang);
    }
    // ...
}
```

---

## Authentication & authorization

Auth state is exposed via the `Auth` facade and the `auth()` helper.

```php
use Zero\Lib\Auth\Auth;

public function dashboard(): Response
{
    $user = Auth::user();             // null if guest
    if (! $user) {
        return Response::redirect('/login');
    }

    return view('pages.dashboard', compact('user'));
}
```

For routes that should always require a logged-in user, attach `AuthMiddleware` once in the route definition rather than checking inside every method:

```php
Router::group(['middleware' => [AuthMiddleware::class]], function () {
    Router::get('/dashboard', [DashboardController::class, 'index']);
    Router::post('/logout', [AuthController::class, 'logout']);
});
```

Inside a guarded controller you can assume `Auth::user()` is non-null.

For per-action checks (admin-only delete, owner-only edit) the simplest pattern is an inline guard:

```php
public function destroy(int $id): Response
{
    $post = Post::findOrFail($id);

    abort_unless(Auth::user()?->id === $post->user_id, 403);

    $post->delete();

    return Response::redirectRoute('posts.index');
}
```

`abort()` and `abort_unless()` raise an HTTP exception that the framework's error handler renders as a 4xx page (HTML) or JSON (when the request expects JSON).

---

## Sessions, cookies, headers

Read via `$request->*`, write via the facades:

```php
use Zero\Lib\Session;
use Zero\Lib\Cookie;

// Session
$flash = $request->session('flash.success');
Session::set('flash.success', 'Saved!');
Session::remove('flash.success');

// Cookie
$theme = $request->cookie('theme', 'light');
Cookie::set('theme', 'dark', minutes: 60 * 24 * 365);

// Outgoing headers
return Response::json($data)->withHeaders([
    'X-Trace-Id' => $traceId,
    'Cache-Control' => 'no-store',
]);
```

The `session()` and `cookie()` global helpers also work in controllers and views.

---

## File uploads

`Request::file($name)` returns an `UploadedFile` (or `null` when the field is absent or the upload failed):

```php
public function store(Request $request): Response
{
    $request->validate([
        'avatar' => ['required', 'file', 'mimes:jpg,png', 'max:2048'],
    ]);

    $upload = $request->file('avatar');
    $path   = Storage::putFile('avatars', $upload, 's3');

    Auth::user()->update(['avatar_path' => $path]);

    return Response::redirectRoute('profile');
}
```

`Storage::putFile` generates a unique filename; use `putFileAs` if you want to pin the name (e.g. `user-42.jpg`). See [storage.md](storage.md) for the full storage API.

For multi-file uploads:

```php
foreach ($request->files()['photos'] ?? [] as $photo) {
    Storage::putFile('gallery', $photo);
}
```

---

## Streaming & file downloads

Hand back a `Response` built by the storage layer for sane streaming (no buffering, correct headers):

```php
public function download(int $id): Response
{
    $report = Report::findOrFail($id);

    return Storage::response($report->path, 's3', [
        'name' => "report-{$report->id}.pdf",
        'disposition' => 'attachment',
        'headers' => ['Cache-Control' => 'private, max-age=0'],
    ]);
}
```

Or stream a generated body:

```php
public function csv(): Response
{
    return Response::stream(function () {
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'email', 'created_at']);
        foreach (User::query()->cursor() as $user) {
            fputcsv($out, [$user->id, $user->email, $user->created_at]);
        }
        fclose($out);
    }, 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="users.csv"',
    ]);
}
```

---

## Dispatching jobs from a controller

Move work out of the request lifecycle by dispatching a queue job. The controller returns immediately; the job runs on a worker (or after the response, if you choose).

```php
use App\Jobs\SendOrderReceipt;

public function checkout(Request $request): Response
{
    $order = Order::create($request->validate([...]));

    // Standard dispatch — runs on a worker
    SendOrderReceipt::dispatch($order->id);

    // Or: do it after the HTTP response has been flushed
    SendOrderReceipt::dispatchAfterResponse($order->id)->onConnection('sync');

    return Response::redirectRoute('orders.show', ['id' => $order->id]);
}
```

See [queue.md](queue.md) for the full queue API.

---

## Resource controllers (REST)

Zero doesn't have an automatic `Router::resource()` helper, but the convention is to use a single controller per resource with the seven standard methods and explicit route registrations:

```php
namespace App\Controllers;

use App\Models\Post;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;

class PostController
{
    public function index(): Response          { /* GET    /posts          */ }
    public function create(): Response         { /* GET    /posts/create   */ }
    public function store(Request $r): Response { /* POST   /posts          */ }
    public function show(int $id): Response    { /* GET    /posts/{id}     */ }
    public function edit(int $id): Response    { /* GET    /posts/{id}/edit */ }
    public function update(Request $r, int $id): Response { /* PUT/PATCH /posts/{id} */ }
    public function destroy(int $id): Response { /* DELETE /posts/{id}     */ }
}
```

```php
// routes/web.php
use App\Controllers\PostController;

Router::get   ('/posts',           [PostController::class, 'index'])->name('posts.index');
Router::get   ('/posts/create',    [PostController::class, 'create'])->name('posts.create');
Router::post  ('/posts',           [PostController::class, 'store'])->name('posts.store');
Router::get   ('/posts/{id}',      [PostController::class, 'show'])->name('posts.show');
Router::get   ('/posts/{id}/edit', [PostController::class, 'edit'])->name('posts.edit');
Router::put   ('/posts/{id}',      [PostController::class, 'update'])->name('posts.update');
Router::patch ('/posts/{id}',      [PostController::class, 'update']);
Router::delete('/posts/{id}',      [PostController::class, 'destroy'])->name('posts.destroy');
```

### Single-action controllers

If a class has exactly one action, give it an `__invoke()` method and skip the verb:

```php
class HealthCheckController
{
    public function __invoke(): Response
    {
        return Response::json(['ok' => true]);
    }
}

Router::get('/healthz', HealthCheckController::class);
```

---

## Sub-namespaces and grouping

Group related controllers under a sub-namespace and prefix their routes:

```bash
php zero make:controller Admin/UserController
php zero make:controller Admin/SettingsController
```

```php
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\SettingsController;
use App\Middlewares\AdminMiddleware;

Router::group([
    'prefix' => '/admin',
    'name' => 'admin',
    'middleware' => [AuthMiddleware::class, AdminMiddleware::class],
], function () {
    Router::get   ('/users',        [UserController::class, 'index'])->name('users.index');
    Router::get   ('/users/{id}',   [UserController::class, 'show'])->name('users.show');
    Router::patch ('/users/{id}',   [UserController::class, 'update'])->name('users.update');

    Router::get   ('/settings',     [SettingsController::class, 'show'])->name('settings.show');
    Router::patch ('/settings',     [SettingsController::class, 'update'])->name('settings.update');
});
```

The middleware applies to every route in the group; the prefix is added to every URL; the name is prepended (so `users.index` becomes `admin.users.index`).

See [router.md](router.md) for the full router API.

---

## Testing controllers

Controllers are plain PHP classes with no framework constructor — instantiate them directly in tests and call methods with a faked `Request`.

```php
use Zero\Lib\Http\Request;

public function testIndexReturnsOkJson(): void
{
    Request::replace([
        'method' => 'GET',
        'uri' => '/posts',
        'query' => ['page' => 1],
    ]);

    $controller = new PostController();
    $response = $controller->index(Request::instance());

    $this->assertSame(200, $response->getStatus());
    $this->assertStringContainsString('"page":1', $response->getContent());
}
```

For end-to-end coverage (full router + middleware), use the built-in dev server (`php zero serve`) and hit it with the HTTP client documented in [support/http.md](support/http.md).

---

## Common patterns

### Form-then-redirect (Post-Redirect-Get)

Validate, persist, flash, redirect. The next GET picks up the flash and renders.

```php
public function store(Request $request): Response
{
    $data = $request->validate([
        'title' => ['required', 'string'],
        'body'  => ['required', 'string'],
    ]);

    Post::create($data + ['user_id' => Auth::user()->id]);

    Session::set('flash.success', 'Post created.');

    return Response::redirectRoute('posts.index');
}
```

### Conditional response shape

Same controller serving HTML and JSON:

```php
public function show(Request $request, int $id): Response
{
    $post = Post::findOrFail($id);

    if ($request->wantsJson()) {
        return Response::json($post);
    }

    return view('posts.show', compact('post'));
}
```

### Long-running work → queue

```php
public function generate(Request $request): Response
{
    $report = Report::create($request->validate([...]));

    GenerateReport::dispatch($report);

    return Response::redirectRoute('reports.show', ['id' => $report->id])
        ->withHeaders(['X-Status' => 'pending']);
}
```

### After-response analytics

```php
public function show(Request $request, int $id): Response
{
    $post = Post::findOrFail($id);

    TrackPageview::dispatchAfterResponse($post->id, Auth::user()?->id ?? 0)
        ->onConnection('sync');

    return view('posts.show', compact('post'));
}
```

---

## Tips

- **No base controller.** Don't extend a framework class; controllers are just PHP. Inject what you need, or grab `Request::instance()` / `Auth::user()` where it's clearer than threading them through.
- **One responsibility per method.** A controller method's job is to receive the request, hand off to a service/model/job, and shape the response. Heavy logic belongs in `App\Services\*`.
- **Type-hint scalars.** PHP coerces route parameters when you type-hint them — no manual `(int) $id` in every method.
- **Flash through the session, not the URL.** `Session::set('flash.success', ...)` + redirect beats `?msg=...` in query strings.
- **Don't catch `ValidationException` in API controllers.** The framework renders a 422 automatically when the request expects JSON. Catch only when you want to flash + redirect for HTML forms.
- **Keep controllers thin.** If a method is more than ~30 lines, look for a service it should delegate to.
- **Name routes.** `route('posts.show', ['id' => $post->id])` survives URL changes; hard-coded `/posts/42` does not.
