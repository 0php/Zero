# DBML Query Builder

Database Management Layer (DBML) is the fluent SQL builder that powers models, console helpers, and ad-hoc data scripts in Zero. It wraps the framework's PDO bridge, so the same chains work against MySQL, PostgreSQL, or SQLite without vendor conditionals.

```php
use Zero\Lib\DB\DBML;

$rows = DBML::table('users as u')
    ->select('u.id', 'u.name', 'u.email')
    ->where('u.active', 1)
    ->orderByDesc('u.created_at')
    ->limit(10)
    ->get();
```

## Selecting Columns

- `select()` accepts strings, arrays, or `DBML::raw()` expressions.
- `addSelect()` appends columns without resetting the previous selection.
- `selectRaw()` records raw fragments while still binding parameters you pass alongside.

```php
$users = DBML::table('users')
    ->select('id', 'name')
    ->addSelect('email')
    ->selectRaw('COUNT(*) over () as total_count')
    ->get();
```

## Filtering Data

Builder methods compose WHERE clauses intuitively:

```php
$users = DBML::table('users')
    ->where('status', 'active')
    ->orWhere(fn ($query) =>
        $query->whereBetween('age', [18, 25])
              ->whereNull('deleted_at')
    )
    ->whereIn('country', ['US', 'CA'])
    ->whereInSet('roles', ['author', 'editor']) // FIND_IN_SET style
    ->when($request->input('q'), function ($query, $term) {
        $query->where('name', 'LIKE', "%{$term}%");
    })
    ->get();
```

Convenience helpers include:

- `whereAny($columns, $value)` / `orWhereAny()` – match the value against any column in the list.
- `whereAnyLike($columns, $value, wildcard: 'both')` / `orWhereAnyLike()` – shorthand for multi-column LIKE checks.
- `whereIn()`, `whereNotIn()`, `whereInSet()`, `whereBetween()`, `whereNull()`, and their `or...` variants.
- `whereExists()` / `whereNotExists()` with nested queries via closures.
- `whereNested(fn ($q) => ...)` and `when()` for clean conditional logic.

## Joining Tables

```php
$posts = DBML::table('posts as p')
    ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
    ->select('p.title', 'u.name as author')
    ->orderBy('p.published_at', 'desc')
    ->get();
```

Supported joins: `join()` (inner), `leftJoin()`, and `rightJoin()`. Provide aliases either inline (`'users as u'`) or via the optional arguments.

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

### Ordering & Pagination

```php
$paginated = DBML::table('users')
    ->orderByDesc('created_at')
    ->paginate(perPage: 20, page: $currentPage);

foreach ($paginated->items() as $user) {
    // ...
}
```

- `orderBy()`, `orderByDesc()`, and `orderByRaw()`.
- `limit()`, `offset()`, `forPage()` for manual pagination.
- `paginate()` issues a total count; `simplePaginate()` skips it for faster infinite-scroll views. Both return `Zero\Lib\Support\Paginator` with helpers (`items()`, `total()`, `perPage()`, `hasMorePages()`, ...).

## Mutating Data

```php
DBML::table('users')->insert([
    'name' => 'Ada Lovelace',
    'email' => 'dev@zerophp.com',
]);

DBML::table('users')
    ->where('id', $id)
    ->update(['last_login_at' => now()]);

DBML::table('sessions')
    ->where('expired_at', '<', now())
    ->delete();
```

- `insert()` accepts a single associative array or an array of rows; returns the last insert ID reported by the driver where available.
- `update()` and `delete()` return the number of affected rows.
- `updateOrCreate($attributes, $values)` finds a matching row (respecting prior constraints), updates it using the merged `$attributes + $values`, or inserts a new row and returns the resulting record as an associative array.
- `findOrCreate($attributes, $values)` returns the first matching row or inserts one using the merged payload when nothing exists.
- Wrap several statements in a transaction via the `Zero\Lib\Database` facade when you need atomic work.

## Raw Expressions & Debugging

```php
$query = DBML::table('orders')
    ->select('id', DBML::raw('JSON_EXTRACT(meta, "$.tracking") as tracking'))
    ->whereRaw('total > ?', [100]);

$sql = $query->toSql();          // SELECT id, JSON_EXTRACT(...) FROM ...
$bindings = $query->getBindings();
```

`DBML::raw()` lets you embed vendor-specific syntax while the builder still handles bindings everywhere else. `toSql()` reveals the generated SQL with placeholders and `getBindings()` exposes the values that will be bound at execution time.

## Working Alongside Models

`Zero\Lib\Model\Model` delegates to DBML under the hood. Use `Model::query()` for hydrated models and drop down to DBML whenever you need raw arrays or advanced constructs:

```php
use App\Models\User;

$recent = User::query()
    ->where('active', 1)
    ->orderByDesc('created_at')
    ->forPage(1, 10)
    ->get();        // array of User instances

$rawRows = User::query()->toBase()->get(); // underlying DBML builder

// Upsert convenience: locate a user by email, update or create in one call
$user = DBML::table('users')->updateOrCreate(
    ['email' => 'dev@zerophp.com'],
    ['name' => 'Ada Lovelace']
); // identifying columns are merged with updates; returns an associative array

// Retrieve or bootstrap a related profile with sensible defaults
$profile = DBML::table('profiles')->findOrCreate(
    ['user_id' => $user['id'] ?? null],
    ['nickname' => 'adal']
);
```

See the [Models guide](models.md) for relationship helpers, lifecycle hooks, and attribute utilities. For deeper internals inspect `core/libraries/DB/QueryBuilder.php`; the `DBML` facade simply forwards to that class.

## Feature Checklist

- Fluent, chainable API for SELECT / INSERT / UPDATE / DELETE.
- Rich filtering: nested closures, multi-column helpers, `whereIn`, `whereBetween`, `whereNull`, `whereExists`, and conditionally applied clauses via `when()`.
- Join support with alias handling and automatic identifier quoting.
- Aggregation helpers (`count`, `exists`, `value`, `pluck`) and HAVING support.
- Pagination primitives plus ready-to-use paginator objects.
- Debug tooling via `toSql()` and `getBindings()`.

DBML stays intentionally small—no global state, no magic. Compose precise SQL with predictable behaviour, and reach for models only when you want higher-level conveniences.
