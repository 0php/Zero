# Routing

The router maps URIs to controller actions, runs middleware, and normalises responses. Implementation: [`core/libraries/Router/Router.php`](../core/libraries/Router/Router.php).

```php
use Zero\Lib\Router;
```

Every verb method returns a `RouteDefinition` you can chain on (`->name()`, `->middleware()`).

---

## Defining routes

### `Router::get(string $route, array $action, mixed $middlewares = null): RouteDefinition`
Register a GET route.
```php
use App\Controllers\UserController;

Router::get('/users', [UserController::class, 'index']);
Router::get('/users/{id}', [UserController::class, 'show']);
```

### `Router::post(string $route, array $action, mixed $middlewares = null): RouteDefinition`
```php
Router::post('/users', [UserController::class, 'store']);
```

### `Router::put(string $route, array $action, mixed $middlewares = null): RouteDefinition`
```php
Router::put('/users/{id}', [UserController::class, 'update']);
```

### `Router::patch(string $route, array $action, mixed $middlewares = null): RouteDefinition`
```php
Router::patch('/users/{id}', [UserController::class, 'patch']);
```

### `Router::delete(string $route, array $action, mixed $middlewares = null): RouteDefinition`
```php
Router::delete('/users/{id}', [UserController::class, 'destroy']);
```

### Path parameters

Wrap segments in `{}`. They're injected by name into the controller signature.
```php
// routes/web.php
Router::get('/users/{id}/posts/{postId}', [PostController::class, 'show']);

// app/controllers/PostController.php
public function show(int $id, int $postId, Request $request) { /* ... */ }
```

The shared `Request` instance is auto-injected when type-hinted.

---

## Route groups

### `Router::group(array $attributes, callable $callback): void`
Apply shared `prefix`, `middleware`, and `name` to a batch of routes.
```php
Router::group(['prefix' => '/admin', 'middleware' => 'auth'], function () {
    Router::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Router::post('/users', [AdminUserController::class, 'store'])->name('users.store');
});
```

Groups can nest:
```php
Router::group(['prefix' => '/api/v1', 'name' => 'api.'], function () {
    Router::group(['middleware' => 'auth'], function () {
        Router::get('/me', [MeController::class, 'show'])->name('me');
        // → name 'api.me', URI '/api/v1/me'
    });
});
```

---

## Domains

### `Router::domain(string|array $domains): DomainRouteGroup`
Route by host. Supports a single domain or a list.
```php
Router::domain('admin.example.com')->group(function () {
    Router::get('/', [AdminDashboardController::class, 'index']);
});

Router::domain(['{tenant}.example.com'])->group(function () {
    Router::get('/dashboard', [TenantDashboardController::class, 'index']);
}, ['middleware' => 'tenant']);
```

The host's wildcard segments (e.g. `{tenant}`) are passed to the controller alongside path parameters.

---

## Naming routes

### `RouteDefinition::name(string $name): self`
```php
Router::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
```

Build URLs with the global `route()` helper or `Router::route()`:
```php
route('users.show', ['id' => 42]);                       // '/users/42'
Router::route('users.show', ['id' => 42], absolute: true);
```

### `Router::route(string $name, array $parameters = [], bool $absolute = true): string`
```php
$url = Router::route('users.show', ['id' => 42]); // 'http://example.test/users/42'
```

---

## Middleware

Middlewares are FQCNs; each must expose a `handle(Request $request, Closure $next): mixed`. A short-circuit (returning a `Response`) bypasses the next layer.

### `RouteDefinition::middleware(mixed $middlewares): self`
Attach one or more.
```php
Router::get('/dashboard', [HomeController::class, 'index'])
    ->middleware(\App\Middlewares\Auth::class);

Router::post('/users', [UserController::class, 'store'])
    ->middleware([\App\Middlewares\Auth::class, \App\Middlewares\VerifyCsrf::class]);
```

### `Router::appendRouteMiddleware(string $method, string $path, mixed $middlewares): void`
Internal helper used by groups; you generally don't call this directly.

### Example middleware

```php
namespace App\Middlewares;

use Closure;
use Zero\Lib\Auth\Auth;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! Auth::user()) {
            return Response::redirect('/login');
        }
        return $next($request);
    }
}
```

---

## Dispatch

### `Router::dispatch(string $requestUri, string $requestMethod): Response`
Resolve a request to a `Response`. Called by [`public/index.php`](../public/index.php); you don't normally call it yourself.
```php
$response = Router::dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
$response->send();
```

### `Router::getRoutes(): array`
Inspect the registered route table (useful for `route:list`-style tooling).
```php
foreach (Router::getRoutes() as $method => $byPath) {
    foreach ($byPath as $path => $route) {
        echo "$method $path\n";
    }
}
```

### `Router::registerRouteName(string $method, string $path, string $name, string $prefix = ''): void`
Internal API used by `RouteDefinition::name()` to wire names into the registry.

---

## Putting it together

```php
// routes/web.php
use App\Controllers\Auth\AuthController;
use App\Controllers\UserController;
use App\Middlewares\Auth as AuthMiddleware;
use Zero\Lib\Router;

Router::get('/login',  [AuthController::class, 'showLoginForm'])->name('login');
Router::post('/login', [AuthController::class, 'login']);

Router::group(['middleware' => AuthMiddleware::class], function () {
    Router::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Router::group(['prefix' => '/users', 'name' => 'users.'], function () {
        Router::get('/',         [UserController::class, 'index'])->name('index');
        Router::get('/{id}',     [UserController::class, 'show'])->name('show');
        Router::put('/{id}',     [UserController::class, 'update'])->name('update');
        Router::delete('/{id}',  [UserController::class, 'destroy'])->name('destroy');
    });
});
```
