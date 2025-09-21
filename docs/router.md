# Routing

The router orchestrates HTTP traffic by mapping URIs to controller methods, executing middleware, and normalising responses.

## Defining Routes

Routes live in `routes/web.php` and use familiar HTTP verb helpers:

```php
use Zero\Lib\Router;
use App\Controllers\HomeController;

Router::get('/', [HomeController::class, 'index']);
Router::post('/profiles', [ProfilesController::class, 'store']);
```

## Route Groups, Prefixes, and Middleware

Group routes with shared attributes:

```php
Router::group(['prefix' => '/dashboard', 'middleware' => AuthMiddleware::class], function () {
    Router::get('/', [DashboardController::class, 'index']);
    Router::get('/reports', [ReportsController::class, 'index']);
});
```

- `prefix` strings are concatenated for nested groups.
- `middleware` can be a single class or an array. Each middleware class must expose a `handle()` method.

## Parameter Binding

Path segments wrapped in `{}` are captured and passed to the controller in order. The router uses reflection to type-cast route parameters:

```php
Router::get('/users/{id}', [UsersController::class, 'show']);
```

```php
class UsersController
{
    public function show(int $id)
    {
        return DBML::table('users')->where('id', $id)->first();
    }
}
```

## Dependency Injection

Controllers and middleware can type-hint `Zero\Lib\Http\Request`. The router detects non-built-in parameter types and injects the shared request instance automatically.

## Middleware Short-Circuiting

If a middleware returns a value, the router resolves it into a `Response` and stops invoking further middlewares or the controller. This pattern is ideal for authentication checks:

```php
class AuthMiddleware
{
    public function handle(Request $request)
    {
        if (!Session::has('user')) {
            return Response::redirect('/login');
        }

        Request::set('current_user', Session::get('user'));
    }
}
```

Middleware can use request attributes to share expensive work with controllers or subsequent middleware. See [Request Attributes](request-response.md#request-attributes) for the full API.

You can also pass parameters to middleware when registering routes:

```php
Router::group(['middleware' => [AuthMiddleware::class, [RoleMiddleware::class, 'admin']]], function () {
    Router::get('/dashboard', [DashboardController::class, 'index']);
});
```

In this example `RoleMiddleware::handle()` receives the current request followed by `'admin'`. Any additional arguments declared after the request will be resolved from the route definition order.

## Error Handling

Unexpected exceptions during route matching or controller execution are logged via the configurable logger and rendered through the central error handler (JSON for API clients, HTML error views otherwise).
