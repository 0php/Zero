# Arr

Static array helpers. Namespace `Zero\Lib\Support\Arr` (aliased globally as `Arr`).

```php
use Zero\Lib\Support\Arr;
```

Topics: [Access](#access) · [Iteration](#iteration) · [Shape](#shape) · [Sorting](#sorting) · [Tests](#tests)

---

## Access

Implementation: [`Concerns/Arr/Access.php`](../../core/libraries/Support/Concerns/Arr/Access.php).

### `accessible(mixed $value): bool`
True for arrays and `ArrayAccess` instances.
```php
Arr::accessible([]);        // true
Arr::accessible('string');  // false
```

### `add(array $array, string|int $key, mixed $value): array`
Set only when the key does not yet exist. Supports dot-notation.
```php
Arr::add(['a' => 1], 'b', 2); // ['a' => 1, 'b' => 2]
Arr::add(['a' => 1], 'a', 9); // ['a' => 1] (unchanged)
```

### `exists(array|ArrayAccess $array, string|int $key): bool`
Direct `array_key_exists` (no dot notation).
```php
Arr::exists(['a' => null], 'a'); // true
```

### `get($array, $key = null, $default = null): mixed`
Read with dot-notation, fall back to `$default`.
```php
Arr::get(['user' => ['name' => 'Tofik']], 'user.name'); // 'Tofik'
Arr::get([], 'x', 'fallback');                          // 'fallback'
```

### `set(array &$array, $key, $value): array`
Write with dot-notation; intermediate arrays are created.
```php
$payload = [];
Arr::set($payload, 'meta.tags', ['php', 'zero']);
// $payload === ['meta' => ['tags' => ['php', 'zero']]]
```

### `has($array, string|array $keys): bool`
Every dot-notated key must exist.
```php
Arr::has(['a' => ['b' => 1]], 'a.b');     // true
Arr::has(['a' => 1], ['a', 'b']);          // false
```

### `hasAny($array, string|array $keys): bool`
Any dot-notated key exists.
```php
Arr::hasAny(['a' => 1], ['x', 'a']); // true
```

### `forget(array &$array, $keys): void`
Remove keys (dot-notation supported).
```php
$a = ['user' => ['name' => 'X', 'age' => 9]];
Arr::forget($a, 'user.name');
// $a === ['user' => ['age' => 9]]
```

### `pull(array &$array, $key, $default = null): mixed`
Read and remove.
```php
$a = ['name' => 'Tofik'];
$name = Arr::pull($a, 'name'); // 'Tofik'; $a === []
```

---

## Iteration

Implementation: [`Concerns/Arr/Iteration.php`](../../core/libraries/Support/Concerns/Arr/Iteration.php).

### `map(array $array, callable $cb): array`
Map preserving keys. Callback receives `($value, $key)`.
```php
Arr::map([1, 2, 3], fn($v) => $v * 2); // [2, 4, 6]
```

### `mapWithKeys(array $array, callable $cb): array`
Callback returns `[$key => $value]` to remap keys.
```php
Arr::mapWithKeys(
    [['id' => 1, 'n' => 'a']],
    fn($v) => [$v['id'] => $v['n']]
); // [1 => 'a']
```

### `where(array $array, callable $cb): array`
Filter using `($value, $key)`.
```php
Arr::where([1, 2, 3, 4], fn($v) => $v % 2 === 0); // [1 => 2, 3 => 4]
```

### `whereNotNull(array $array): array`
Drop entries whose value is `null`.
```php
Arr::whereNotNull([1, null, 3]); // [0 => 1, 2 => 3]
```

### `partition(array $array, callable $cb): array`
Split into `[passing, failing]`.
```php
[$evens, $odds] = Arr::partition([1, 2, 3, 4], fn($v) => $v % 2 === 0);
// $evens === [1 => 2, 3 => 4]; $odds === [0 => 1, 2 => 3]
```

### `first(iterable $array, ?callable $cb = null, $default = null): mixed`
First item, or first match of `$cb`.
```php
Arr::first([1, 2, 3]);                          // 1
Arr::first([1, 2, 3], fn($v) => $v > 1);        // 2
Arr::first([], null, 'fallback');               // 'fallback'
```

### `last(array $array, ?callable $cb = null, $default = null): mixed`
```php
Arr::last([1, 2, 3]);                           // 3
Arr::last([1, 2, 3], fn($v) => $v < 3);         // 2
```

---

## Shape

Implementation: [`Concerns/Arr/Shape.php`](../../core/libraries/Support/Concerns/Arr/Shape.php).

### `collapse(iterable $array): array`
Merge a list of arrays.
```php
Arr::collapse([[1, 2], [3, 4]]); // [1, 2, 3, 4]
```

### `flatten(iterable $array, int $depth = PHP_INT_MAX): array`
Flatten nested arrays.
```php
Arr::flatten([1, [2, [3]]]);     // [1, 2, 3]
Arr::flatten([1, [2, [3]]], 1);  // [1, 2, [3]]
```

### `dot(iterable $array, string $prepend = ''): array`
Convert nested arrays to dot-keyed.
```php
Arr::dot(['a' => ['b' => 1]]); // ['a.b' => 1]
```

### `undot(array $array): array`
Inverse of `dot()`.
```php
Arr::undot(['a.b' => 1]); // ['a' => ['b' => 1]]
```

### `wrap(mixed $value): array`
Wrap non-arrays. `null` becomes `[]`.
```php
Arr::wrap('a');     // ['a']
Arr::wrap(null);    // []
Arr::wrap([1, 2]);  // [1, 2]
```

### `pluck(iterable $array, $value, $key = null): array`
Extract a column. `$value` and `$key` may be dot-paths.
```php
$users = [['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b']];
Arr::pluck($users, 'name');         // ['a', 'b']
Arr::pluck($users, 'name', 'id');   // [1 => 'a', 2 => 'b']
```

### `keyBy(array $array, callable|string $keyBy): array`
Index by a field or callback.
```php
Arr::keyBy([['id' => 1], ['id' => 2]], 'id');
// [1 => ['id' => 1], 2 => ['id' => 2]]
```

### `only(array $array, $keys): array` / `except(array $array, $keys): array`
```php
Arr::only(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'c']); // ['a' => 1, 'c' => 3]
Arr::except(['a' => 1, 'b' => 2], 'a');                // ['b' => 2]
```

### `take(array $array, int $limit): array`
First or last `n` items (negative for tail).
```php
Arr::take([1, 2, 3, 4], 2);   // [1, 2]
Arr::take([1, 2, 3, 4], -2);  // [3, 4]
```

### `prepend(array $array, mixed $value, $key = null): array`
```php
Arr::prepend(['b', 'c'], 'a');                  // ['a', 'b', 'c']
Arr::prepend(['b' => 2], 1, 'a');               // ['a' => 1, 'b' => 2]
```

### `push(array $array, mixed ...$values): array`
```php
Arr::push([1, 2], 3, 4); // [1, 2, 3, 4]
```

### `divide(array $array): array`
Split into `[keys, values]`.
```php
Arr::divide(['a' => 1, 'b' => 2]); // [['a', 'b'], [1, 2]]
```

### `crossJoin(array ...$arrays): array`
Cartesian product.
```php
Arr::crossJoin([1, 2], ['a', 'b']);
// [[1,'a'], [1,'b'], [2,'a'], [2,'b']]
```

---

## Sorting

Implementation: [`Concerns/Arr/Sorting.php`](../../core/libraries/Support/Concerns/Arr/Sorting.php).

### `sort(array $array, callable|string|null $callback = null): array`
`asort` when no callback. With a string, treat it as a dot-notation field. With a callable, use it as a value resolver.
```php
Arr::sort([3, 1, 2]);                           // [1 => 1, 2 => 2, 0 => 3]
Arr::sort([['n' => 3], ['n' => 1]], 'n');       // sorted by n
```

### `sortDesc(array $array, callable|string|null $callback = null): array`
```php
Arr::sortDesc([1, 3, 2]); // [1 => 3, 2 => 2, 0 => 1]
```

### `sortRecursive(array $array, int $options = SORT_REGULAR, bool $descending = false): array`
Sort at every depth.
```php
Arr::sortRecursive([3, 1, [5, 4]]); // [1, 3, [4, 5]]
```

### `shuffle(array $array, ?int $seed = null): array`
Seedable for tests.
```php
Arr::shuffle([1, 2, 3, 4, 5]);       // random
Arr::shuffle([1, 2, 3, 4, 5], 42);   // deterministic
```

### `random(array $array, ?int $number = null, bool $preserveKeys = false): mixed`
Pull random items. Without `$number`, returns a single item.
```php
Arr::random([1, 2, 3, 4, 5]);    // e.g. 3
Arr::random([1, 2, 3, 4, 5], 3); // 3 random items (re-indexed)
```

---

## Tests

Implementation: [`Concerns/Arr/Tests.php`](../../core/libraries/Support/Concerns/Arr/Tests.php).

### `isAssoc(array $array): bool`
```php
Arr::isAssoc(['a' => 1]);  // true
Arr::isAssoc([1, 2, 3]);   // false
```

### `isList(array $array): bool`
```php
Arr::isList([1, 2, 3]);     // true
Arr::isList(['a' => 1]);    // false
```

### `join(array $array, string $glue, string $finalGlue = ''): string`
Implode with an optional separator before the last item.
```php
Arr::join(['a', 'b', 'c'], ', ');           // 'a, b, c'
Arr::join(['a', 'b', 'c'], ', ', ' and ');  // 'a, b and c'
```

### `query(array $array): string`
Build an RFC-3986 query string.
```php
Arr::query(['a' => 1, 'b' => 2]); // 'a=1&b=2'
Arr::query(['filter' => ['x', 'y']]); // 'filter%5B0%5D=x&filter%5B1%5D=y'
```
