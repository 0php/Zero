# DBML Query Builder

Database Management Layer (DBML) is the fluent SQL builder that powers models, console helpers, and ad-hoc data scripts. It wraps the framework's PDO bridge, so the same chains work against MySQL, PostgreSQL, or SQLite.

```php
use Zero\Lib\DB\DBML;
```

`DBML` extends `QueryBuilder`, so every builder method is available statically (`DBML::table(...)`) or fluently. Implementation: [`DBML.php`](../core/libraries/DB/DBML.php), [`QueryBuilder.php`](../core/libraries/DB/QueryBuilder.php), [`Concerns/HandlesWhereClauses.php`](../core/libraries/DB/Concerns/HandlesWhereClauses.php).

---

## Building a query

### `DBML::table(string $table, ?string $alias = null): self`
Start a new query.
```php
$users = DBML::table('users')->get();
$u = DBML::table('users', 'u')->get();
```

### `->from(string $table, ?string $alias = null): self`
Same as `table()`, fluent form.
```php
DBML::table('users')->from('archived_users')->get();
```

### `->select(string|array|DBMLExpression ...$columns): self`
```php
DBML::table('users')->select('id', 'email')->get();
DBML::table('users')->select(['id', 'email'])->get();
```

### `->addSelect(string|array|DBMLExpression ...$columns): self`
Append columns to the existing select list.
```php
DBML::table('users')->select('id')->addSelect('email')->get();
```

### `->selectRaw(string $expression, array $bindings = []): self`
```php
DBML::table('users')
    ->selectRaw('COUNT(*) as total, ? as label', ['active'])
    ->get();
```

### `DBML::raw(string $expression): DBMLExpression`
Wrap a raw SQL fragment so it bypasses quoting.
```php
DBML::table('users')
    ->select(DBML::raw('LOWER(email) AS email'))
    ->get();
```

---

## Joins

### `->join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'INNER', ?string $alias = null): self`
```php
DBML::table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title')
    ->get();
```

### `->leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null, ?string $alias = null): self`
```php
DBML::table('users')
    ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();
```

### `->rightJoin(...)`
Same shape as `leftJoin`.
```php
DBML::table('a')->rightJoin('b', 'a.id', '=', 'b.a_id')->get();
```

---

## Where clauses

Provided by [`HandlesWhereClauses`](../core/libraries/DB/Concerns/HandlesWhereClauses.php). Two/three-arg forms supported throughout.

### `->where($column, $operator = null, $value = null, string $boolean = 'AND'): self`
```php
DBML::table('users')->where('active', 1)->get();
DBML::table('users')->where('age', '>', 18)->get();
DBML::table('users')->where(['active' => 1, 'role' => 'admin'])->get();

// Closure for grouped conditions
DBML::table('users')->where(function ($q) {
    $q->where('role', 'admin')->orWhere('role', 'owner');
})->get();
```

### `->orWhere(...)`
```php
DBML::table('users')->where('role', 'admin')->orWhere('role', 'owner')->get();
```

### `->whereAny(...$args): self` / `->orWhereAny(...)`
Match any of multiple columns.
```php
DBML::table('users')->whereAny(['name', 'email'], 'like', '%tofik%')->get();
```

### `->whereAnyLike(...)` / `->orWhereAnyLike(...)`
Convenience for the `LIKE` variant of `whereAny`.
```php
DBML::table('users')->whereAnyLike(['name', 'email'], 'tofik')->get();
```

### `->whereNot(string $column, $value, $boolean = 'AND'): self` / `->orWhereNot(...)`
```php
DBML::table('users')->whereNot('active', 0)->get();
```

### `->whereIn(string $column, array $values, $boolean = 'AND', bool $not = false): self`
```php
DBML::table('users')->whereIn('id', [1, 2, 3])->get();
```

### `->whereNotIn(...)` / `->orWhereIn(...)` / `->orWhereNotIn(...)`
```php
DBML::table('users')->whereNotIn('status', ['deleted'])->get();
```

### `->whereInSet(string $column, array $values, $boolean = 'AND'): self`
For comma-separated SET-style columns (MySQL `SET` / serialized lists).
```php
DBML::table('users')->whereInSet('roles', ['admin'])->get();
```

### `->whereNotInSet(...)` / `->orWhereInSet(...)` / `->orWhereNotInSet(...)`

### `->whereBetween(string $column, array $values, $boolean = 'AND', bool $not = false): self`
```php
DBML::table('orders')->whereBetween('total', [100, 500])->get();
```

### `->whereNotBetween(...)` / `->orWhereBetween(...)` / `->orWhereNotBetween(...)`

### `->whereNull(string $column, $boolean = 'AND', bool $not = false): self`
```php
DBML::table('users')->whereNull('deleted_at')->get();
```

### `->whereNotNull(...)` / `->orWhereNull(...)` / `->orWhereNotNull(...)`
```php
DBML::table('users')->whereNotNull('email_verified_at')->get();
```

### `->whereRaw(string $expression, array $bindings = [], $boolean = 'AND'): self`
```php
DBML::table('users')->whereRaw('LOWER(email) = ?', [$email])->get();
```

### `->whereExists(QueryBuilder $query, $boolean = 'AND'): self` / `->whereNotExists(...)` / `->orWhereExists(...)` / `->orWhereNotExists(...)`
```php
$sub = DBML::table('posts')->whereRaw('posts.user_id = users.id');
DBML::table('users')->whereExists($sub)->get();
```

---

## Ordering

### `->orderBy(string|DBMLExpression $column, string $direction = 'ASC'): self`
```php
DBML::table('users')->orderBy('created_at', 'DESC')->get();
```

### `->orderByDesc(string|DBMLExpression $column): self`
```php
DBML::table('users')->orderByDesc('created_at')->get();
```

### `->orderByRaw(string $expression): self`
```php
DBML::table('users')->orderByRaw('LENGTH(name) ASC')->get();
```

---

## Grouping & aggregates

### `->groupBy(...$columns): self`
```php
DBML::table('orders')
    ->select('status', DBML::raw('COUNT(*) AS total'))
    ->groupBy('status')
    ->get();
```

### `->having($column, $operator = null, $value = null, $boolean = 'AND'): self`
```php
DBML::table('orders')
    ->select('user_id', DBML::raw('SUM(total) AS spent'))
    ->groupBy('user_id')
    ->having('spent', '>', 1000)
    ->get();
```

### `->havingRaw(string $expression, array $bindings = [], $boolean = 'AND'): self`
```php
$query->havingRaw('SUM(total) > ?', [1000]);
```

---

## Pagination & limits

### `->limit(?int $value): self`
```php
DBML::table('users')->limit(10)->get();
```

### `->offset(?int $value): self`
```php
DBML::table('users')->offset(20)->limit(10)->get();
```

### `->forPage(int $page, int $perPage): self`
Convenience: sets limit + offset for the requested page.
```php
DBML::table('users')->forPage(2, 15)->get();
```

### `->paginate(int $perPage = 15, int $page = 1): Paginator`
Returns a `Paginator` with metadata.
```php
$page = DBML::table('users')->where('active', 1)->paginate(20, page: 2);
$page->total();
$page->lastPage();
foreach ($page as $row) { /* ... */ }
```

### `->simplePaginate(int $perPage = 15, int $page = 1): Paginator`
No total count — cheaper.
```php
$page = DBML::table('users')->simplePaginate(15);
```

---

## Conditional chains

### `->when(mixed $value, Closure $callback, ?Closure $default = null): self`
Apply `$callback` only when `$value` is truthy.
```php
$q = DBML::table('users')
    ->when($search, fn ($q) => $q->whereAnyLike(['name', 'email'], $search))
    ->when($onlyActive, fn ($q) => $q->where('active', 1))
    ->get();
```

---

## Reading data

### `->get(array|string|DBMLExpression $columns = []): array`
```php
$rows = DBML::table('users')->where('active', 1)->get();
$rows = DBML::table('users')->get(['id', 'email']);
```

### `->first($columns = []): array|null`
```php
$user = DBML::table('users')->where('email', $email)->first();
```

### `->value(string $column): mixed`
First row's column value.
```php
$email = DBML::table('users')->where('id', 42)->value('email');
```

### `->pluck(string $column, ?string $key = null): array`
```php
$emails = DBML::table('users')->pluck('email');                  // ['a@b', 'c@d']
$emails = DBML::table('users')->pluck('email', 'id');            // [1 => 'a@b', 2 => 'c@d']
```

### `->exists(): bool`
```php
if (DBML::table('users')->where('email', $email)->exists()) { /* ... */ }
```

### `->count(string $column = '*'): int`
```php
$total = DBML::table('users')->where('active', 1)->count();
```

---

## Writing data

### `->insert(array $values): mixed`
Insert one row (associative) or many rows (list of associatives). Returns the last insert id when applicable.
```php
$id = DBML::table('users')->insert([
    'email' => 'a@b.test',
    'name'  => 'A',
]);

DBML::table('logs')->insert([
    ['msg' => 'a'],
    ['msg' => 'b'],
]);
```

### `->update(array $values): int`
Returns the number of affected rows.
```php
$affected = DBML::table('users')
    ->where('id', 42)
    ->update(['name' => 'New Name']);
```

### `->delete(): int`
Returns the number of deleted rows.
```php
$gone = DBML::table('logs')->where('created_at', '<', $cutoff)->delete();
```

### `->updateOrCreate(array $attributes, array $values = []): array`
Find by `$attributes`, update or insert with `$values`. Returns the row.
```php
$row = DBML::table('users')->updateOrCreate(
    ['email' => 'a@b.test'],
    ['name' => 'A']
);
```

### `->findOrCreate(array $attributes, array $values = []): array`
Same as `updateOrCreate` but never updates an existing row.
```php
$row = DBML::table('users')->findOrCreate(['email' => 'a@b.test']);
```

---

## Inspection

### `->toSql(): string`
```php
$sql = DBML::table('users')->where('active', 1)->toSql();
```

### `->getBindings(): array`
```php
$bindings = DBML::table('users')->where('active', 1)->getBindings();
```

---

## Transactions

### `DBML::startTransaction(): void` / `DBML::commit(): void` / `DBML::rollback(): void`
```php
DBML::startTransaction();
try {
    DBML::table('orders')->insert($order);
    DBML::table('order_items')->insert($items);
    DBML::commit();
} catch (\Throwable $e) {
    DBML::rollback();
    throw $e;
}
```

---

## DBML expressions

`DBML::raw($expr)` returns a `DBMLExpression` instance you can use anywhere a column or expression is accepted. The wrapper is recognized by all builder methods so it bypasses identifier quoting.

```php
$lower = DBML::raw('LOWER(email) AS email');
DBML::table('users')->select($lower)->get();
```
