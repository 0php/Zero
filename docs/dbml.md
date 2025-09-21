# DBML Query Builder

Database Management Layer (DBML) provides the fluent query interface used throughout the framework. For schema changes, rely on the migration DBAL described in migrations.md.

`Zero\Lib\DB\DBML` is a lightweight, fluent SQL builder that mirrors the ergonomics of Laravel's query builder while keeping dependencies to a minimum. It speaks to the framework's PDO bridge, so the same code runs against MySQL, PostgreSQL, or SQLite with no driver-specific conditionals.

## Getting Started

```php
use Zero\Lib\DB\DBML;

$users = DBML::table('users as u')
    ->select(['u.id', 'u.name', 'u.email'])
    ->where('u.active', 1)
    ->orderByDesc('u.created_at')
    ->limit(10)
    ->get();
```

- Pass `table AS alias` (or the second `$alias` argument) to work with concise column names.
- `get()` returns an array of associative rows, making it easy to return JSON responses or hydrate models manually.

## Selecting Columns

```php
$users = DBML::table('users')
    ->select('id', 'name')
    ->addSelect('email')
    ->selectRaw('COUNT(*) over () as total_count')
    ->get();
```

- `select()` accepts strings, arrays, or `DBML::raw()` expressions.
- `addSelect()` appends more columns without resetting the previous selection.
- `selectRaw()` stores raw fragments while still binding parameters safely.

## Filtering Data

Builder methods compose WHERE clauses intuitively:

```php
$users = DBML::table('users')
    ->where('status', 'active')                // column = value
    ->orWhere(fn ($query) =>                   // nested conditions
        $query->whereBetween('age', [18, 25])
              ->whereNull('deleted_at')
    )
    ->whereIn('country', ['US', 'CA'])
    ->whereInSet('roles', ['author', 'editor']) // match CSV/SET columns using FIND_IN_SET
    ->when($request->input('q'), function ($query, $term) {
        $query->where('name', 'LIKE', "%{$term}%");
    })
    ->get();
```

Key helpers:

- `where()`, `orWhere()`, `whereNot()` for basic comparisons.
- `whereIn()`, `whereNotIn()`, `whereInSet()`, `whereNotInSet()`, `whereNull()`, `whereBetween()` and their `or...` counterparts.
- `whereRaw()` (and `havingRaw()`) for advanced clauses while still binding values manually.
- `when($value, $callback, $default)` to apply conditional logic without `if` blocks.

## Joining Tables

```php
$posts = DBML::table('posts as p')
    ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
    ->select('p.title', 'u.name as author')
    ->orderBy('p.published_at', 'desc')
    ->get();
```

Supported joins: `join()` (inner), `leftJoin()`, and `rightJoin()`. Provide the join alias as part of the table string (`'users as u'`) or via the optional fifth argument.

## Grouping, Aggregates & Existence Checks

```php
$stats = DBML::table('orders')
    ->select('status', DBML::raw('COUNT(*) as total'))
    ->groupBy('status')
    ->having('total', '>', 10)
    ->get();

$totalUsers = DBML::table('users')->count();
$firstEmail = DBML::table('users')->value('email');
$emails = DBML::table('users')->pluck('email');
$hasAdmins = DBML::table('users')->where('role', 'admin')->exists();
```

- `count()` runs a `COUNT(*)` aggregate (or any column you pass).
- `value()` fetches a single column from the first row.
- `pluck()` returns a flat list or key/value map.
- `exists()` stops after the first match for efficient checks.

## Sorting & Pagination

```php
$paginated = DBML::table('users')
    ->orderByDesc('created_at')
    ->paginate(perPage: 20, page: $currentPage);

foreach ($paginated->items() as $user) {
    // ...
}
```

Tools you have at your disposal:

- `orderBy()`, `orderByDesc()`, and `orderByRaw()`
- `limit()`, `offset()`, and `forPage()`
- `paginate($perPage, $page)` – runs an extra `COUNT(*)` to compute totals
- `simplePaginate()` – skips the count when you only care about “has next page”

`paginate()` and `simplePaginate()` return `Zero\Lib\Support\Paginator`, which exposes helpers like `items()`, `total()`, `perPage()`, `currentPage()`, and `hasMorePages()`.

## Writing Data

```php
DBML::table('users')->insert([
    'name' => 'Ada Lovelace',
    'email' => 'ada@example.com',
]);

DBML::table('users')
    ->where('id', $id)
    ->update(['last_login_at' => now()]);

DBML::table('sessions')
    ->where('expired_at', '<', now())
    ->delete();
```

- `insert()` accepts a single associative array or an array of rows; returns the last insert ID reported by the driver.
- `update()` returns the number of affected rows; provide a `where()` clause to avoid touching the entire table.
- `delete()` removes matching rows and returns the affected count.

Every write method uses prepared statements to avoid SQL injection. Wrap multiple operations in a transaction via the `Database` facade if you need atomic behaviour.

## Raw Expressions & Debugging

```php
$query = DBML::table('orders')
    ->select('id', DBML::raw('JSON_EXTRACT(meta, "$.tracking") as tracking'))
    ->whereRaw('total > ?', [100]);

$sql = $query->toSql();          // SELECT id, JSON_EXTRACT(...) FROM ...
$bindings = $query->getBindings();
```

`DBML::raw()` (or the `DBMLExpression` objects it returns) tell the builder not to quote identifiers. `toSql()` reveals the generated SQL with placeholders, while `getBindings()` exposes the values that will be bound at execution time—ideal for logging or debugging.

## Using DBML with Models

`Zero\Lib\Model\Model` delegates to the same builder under the hood. You can drop down to DBML whenever you need more control:

```php
use App\Models\User;

$recent = User::query()
    ->where('active', 1)
    ->orderByDesc('created_at')
    ->forPage(1, 10)
    ->get();        // array of User model instances

$rawRows = User::query()->toBase()->get(); // plain arrays via DBML
```

`Model::query()` starts a builder scoped to the model's table, and `toBase()` gives you direct access to the underlying DBML instance when you want raw arrays instead of model objects.

## Summary of Capabilities

- Fluent, chainable API for SELECT/INSERT/UPDATE/DELETE.
- Rich filtering: nested closures, `whereIn`, `whereBetween`, `whereNull`, and conditionally applied clauses.
- Join support with alias handling and automatic identifier quoting.
- Aggregation helpers (`count`, `exists`, `value`, `pluck`).
- Pagination primitives (`limit`, `offset`, `forPage`) plus ready-to-use `paginate` helpers.
- Debug tooling via `toSql()` and `getBindings()`.

For deeper internals, explore `core/libraries/DB/QueryBuilder.php`—`DBML` simply extends it to expose a concise facade for your application code.
