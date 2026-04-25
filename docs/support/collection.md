# Collection

Fluent, chainable wrapper around arrays. Namespace `Zero\Lib\Support\Collection` (aliased as `Collection`). Build with the global `collect()` helper or `Collection::make()`.

```php
use Zero\Lib\Support\Collection;

collect([1, 2, 3, 4, 5])
    ->filter(fn ($v) => $v > 2)
    ->map(fn ($v) => $v * 10)
    ->values()
    ->all(); // [30, 40, 50]
```

`Collection` implements `ArrayAccess`, `Countable`, `IteratorAggregate`, `JsonSerializable`, so `count($c)`, `foreach ($c as ...)`, `$c[$key]`, and `json_encode($c)` work directly.

Topics: [Building](#building) · [Conversion](#conversion) · [Iteration](#iteration) · [Filtering](#filtering) · [Querying](#querying) · [Mutation](#mutation) · [Slicing](#slicing) · [Reshaping](#reshaping) · [Set Operations](#set-operations) · [Sorting](#sorting) · [Aggregates](#aggregates) · [Conditional](#conditional)

---

## Building

Static factories on `Collection`. Implementation: top of [`Collection.php`](../../core/libraries/Support/Collection.php).

### `collect(mixed $items = []): Collection` / `Collection::make(iterable $items)`
```php
collect([1, 2, 3])->count();                // 3
Collection::make(['a' => 1])->all();        // ['a' => 1]
```

### `Collection::wrap(mixed $value): Collection`
Wrap any value.
```php
Collection::wrap('a')->all(); // ['a']
Collection::wrap(null)->all(); // []
```

### `Collection::range(int $from, int $to): Collection`
```php
Collection::range(1, 3)->all(); // [1, 2, 3]
```

### `Collection::times(int $number, ?callable $callback = null): Collection`
```php
Collection::times(3, fn ($i) => $i * 2)->all(); // [2, 4, 6]
```

---

## Conversion

Implementation: [`Concerns/Collection/Conversion.php`](../../core/libraries/Support/Concerns/Collection/Conversion.php).

### `all(): array`
```php
collect([1, 2, 3])->all(); // [1, 2, 3]
```

### `toArray(): array`
Recursively convert nested Collections / `JsonSerializable`.
```php
collect([collect([1, 2])])->toArray(); // [[1, 2]]
```

### `toJson(int $flags = 0): string` / `jsonSerialize(): array`
```php
collect([1, 2])->toJson();              // '[1,2]'
json_encode(collect([1, 2]));           // '[1,2]'
```

### `count(): int` / `isEmpty(): bool` / `isNotEmpty(): bool`
```php
collect([1, 2])->count();         // 2
collect([])->isEmpty();           // true
collect([1])->isNotEmpty();       // true
count(collect([1, 2, 3]));        // 3
```

### `keys(): Collection` / `values(): Collection`
```php
collect(['a' => 1, 'b' => 2])->keys()->all();   // ['a', 'b']
collect(['a' => 1, 'b' => 2])->values()->all(); // [1, 2]
```

### `ArrayAccess` / `getIterator()`
```php
$c = collect(['a' => 1]);
$c['a'];                // 1
foreach ($c as $k => $v) { /* ... */ }
```

---

## Iteration

Implementation: [`Concerns/Collection/Iteration.php`](../../core/libraries/Support/Concerns/Collection/Iteration.php).

### `map(callable $cb): Collection`
```php
collect([1, 2, 3])->map(fn ($v) => $v * 2)->all(); // [2, 4, 6]
```

### `mapWithKeys(callable $cb): Collection`
```php
collect([['id' => 1, 'n' => 'a']])
    ->mapWithKeys(fn ($v) => [$v['id'] => $v['n']])
    ->all(); // [1 => 'a']
```

### `mapSpread(callable $cb): Collection`
Spread each item's values as positional arguments.
```php
collect([[1, 2], [3, 4]])
    ->mapSpread(fn ($a, $b) => $a + $b)
    ->all(); // [3, 7]
```

### `flatMap(callable $cb): Collection`
Map then `collapse`.
```php
collect([[1, 2], [3]])->flatMap(fn ($v) => $v)->all(); // [1, 2, 3]
```

### `each(callable $cb): self`
Iterate; return `false` from the callback to stop.
```php
collect([1, 2, 3])->each(function ($v) { /* side effect */ });
```

### `eachSpread(callable $cb): self`
```php
collect([[1, 2], [3, 4]])->eachSpread(fn ($a, $b) => /* ... */);
```

### `reduce(callable $cb, $initial = null): mixed`
```php
collect([1, 2, 3])->reduce(fn ($carry, $v) => $carry + $v, 0); // 6
```

### `pluck(string|array|null $value, $key = null): Collection`
```php
collect([['id' => 1], ['id' => 2]])->pluck('id')->all(); // [1, 2]
```

### `partition(callable $cb): Collection`
Returns a Collection of two Collections.
```php
[$evens, $odds] = collect([1, 2, 3, 4])->partition(fn ($v) => $v % 2 === 0);
$evens->all(); // [1 => 2, 3 => 4]
$odds->all();  // [0 => 1, 2 => 3]
```

### `keyBy(callable|string $keyBy): Collection`
```php
collect([['id' => 1], ['id' => 2]])->keyBy('id')->keys()->all(); // [1, 2]
```

### `groupBy(callable|string $groupBy): Collection`
Each group is itself a Collection.
```php
collect([['t' => 'a'], ['t' => 'a'], ['t' => 'b']])
    ->groupBy('t')
    ->count(); // 2
```

---

## Filtering

Implementation: [`Concerns/Collection/Filtering.php`](../../core/libraries/Support/Concerns/Collection/Filtering.php).

### `filter(?callable $cb = null): Collection`
Without a callback, drops falsy values.
```php
collect([0, 1, 2])->filter()->values()->all();                  // [1, 2]
collect([1, 2, 3, 4])->filter(fn ($v) => $v > 2)->values()->all(); // [3, 4]
```

### `reject(callable $cb): Collection`
Inverse of `filter`.
```php
collect([1, 2, 3, 4])->reject(fn ($v) => $v % 2 === 0)->values()->all(); // [1, 3]
```

### `where(string $key, $operator = null, $value = null): Collection`
Two-argument form is `=`. Operators: `=`, `==`, `===`, `!=`, `!==`, `<>`, `>`, `>=`, `<`, `<=`.
```php
$users = [['active' => true], ['active' => false]];
collect($users)->where('active', true)->count(); // 1
collect([['n' => 5], ['n' => 1]])->where('n', '>', 3)->count(); // 1
```

### `whereIn(string $key, iterable $values): Collection` / `whereNotIn(...)`
```php
collect([['t' => 'a'], ['t' => 'b'], ['t' => 'c']])
    ->whereIn('t', ['a', 'c'])
    ->count(); // 2
```

### `whereNotNull(?string $key = null): Collection` / `whereNull(...)`
```php
collect([1, null, 2])->whereNotNull()->values()->all(); // [1, 2]
collect([['n' => 1], ['n' => null]])->whereNull('n')->count(); // 1
```

### `only(array|string $keys): Collection` / `except(...)`
```php
collect(['a' => 1, 'b' => 2, 'c' => 3])->only(['a', 'c'])->all(); // ['a' => 1, 'c' => 3]
```

### `contains($key, $operator = null, $value = null): bool`
Scalar / closure / where form.
```php
collect([1, 2, 3])->contains(2);                    // true
collect([1, 2, 3])->contains(fn ($v) => $v > 2);    // true
collect([['t' => 'a']])->contains('t', 'a');        // true
```

### `has(string|int|array $key): bool`
Returns true only when every key exists.
```php
collect(['a' => 1])->has('a');               // true
collect(['a' => 1])->has(['a', 'b']);        // false
```

---

## Querying

Implementation: [`Concerns/Collection/Querying.php`](../../core/libraries/Support/Concerns/Collection/Querying.php).

### `first(?callable $cb = null, $default = null): mixed` / `last(...)`
```php
collect([1, 2, 3])->first();                          // 1
collect([1, 2, 3])->first(fn ($v) => $v > 1);         // 2
collect([1, 2, 3])->last();                           // 3
```

### `get(string|int $key, $default = null): mixed`
```php
collect(['a' => 1])->get('a');                  // 1
collect([])->get('x', 'fallback');              // 'fallback'
```

### `search(mixed $value, bool $strict = false): mixed`
Returns the matching key (or `false`). Accepts a closure.
```php
collect(['a', 'b', 'c'])->search('b');                 // 1
collect([1, 2, 3])->search(fn ($v) => $v === 2);       // 1
```

### `random(?int $number = null, bool $preserveKeys = false): mixed`
```php
collect([1, 2, 3, 4, 5])->random();      // single item
collect([1, 2, 3, 4, 5])->random(3);     // Collection of 3 items
```

---

## Mutation

These mutate the underlying items in place and return `$this`. Implementation: [`Concerns/Collection/Mutation.php`](../../core/libraries/Support/Concerns/Collection/Mutation.php).

### `put(string|int $key, mixed $value): self`
```php
collect()->put('a', 1)->all(); // ['a' => 1]
```

### `pull(string|int $key, $default = null): mixed`
Read and remove.
```php
$c = collect(['a' => 1, 'b' => 2]);
$c->pull('a'); // 1; $c->all() === ['b' => 2]
```

### `push(mixed ...$values): self`
```php
collect([1])->push(2, 3)->all(); // [1, 2, 3]
```

### `prepend(mixed $value, $key = null): self`
```php
collect([2, 3])->prepend(1)->all();        // [1, 2, 3]
collect(['b' => 2])->prepend(1, 'a')->all(); // ['a' => 1, 'b' => 2]
```

### `pop(int $count = 1): mixed` / `shift(int $count = 1): mixed`
With `$count = 1` returns the item; otherwise returns a Collection.
```php
collect([1, 2, 3])->pop();    // 3
collect([1, 2, 3])->shift();  // 1
```

### `forget(string|int|array $keys): self`
```php
collect(['a' => 1, 'b' => 2])->forget('a')->all(); // ['b' => 2]
```

---

## Slicing

Implementation: [`Concerns/Collection/Slicing.php`](../../core/libraries/Support/Concerns/Collection/Slicing.php).

### `take(int $limit): Collection`
```php
collect([1, 2, 3, 4])->take(2)->all();   // [1, 2]
collect([1, 2, 3, 4])->take(-2)->all();  // [3, 4]
```

### `skip(int $count): Collection`
```php
collect([1, 2, 3, 4])->skip(2)->values()->all(); // [3, 4]
```

### `slice(int $offset, ?int $length = null): Collection`
```php
collect([1, 2, 3, 4])->slice(1, 2)->values()->all(); // [2, 3]
```

### `chunk(int $size): Collection`
Returns a Collection of Collections.
```php
collect([1, 2, 3, 4, 5])->chunk(2)->count(); // 3
```

### `nth(int $step, int $offset = 0): Collection`
```php
collect([1, 2, 3, 4, 5, 6])->nth(2)->all();    // [1, 3, 5]
collect([1, 2, 3, 4, 5, 6])->nth(2, 1)->all(); // [2, 4, 6]
```

---

## Reshaping

Implementation: [`Concerns/Collection/Reshaping.php`](../../core/libraries/Support/Concerns/Collection/Reshaping.php).

### `collapse(): Collection`
Merge a list of arrays.
```php
collect([[1, 2], [3]])->collapse()->all(); // [1, 2, 3]
```

### `flatten(int $depth = PHP_INT_MAX): Collection`
```php
collect([1, [2, [3]]])->flatten()->all(); // [1, 2, 3]
```

### `flip(): Collection`
```php
collect(['a', 'b'])->flip()->all(); // ['a' => 0, 'b' => 1]
```

### `dot(): Collection` / `undot(): Collection`
```php
collect(['a' => ['b' => 1]])->dot()->all(); // ['a.b' => 1]
collect(['a.b' => 1])->undot()->all();      // ['a' => ['b' => 1]]
```

### `reverse(): Collection`
Preserves keys.
```php
collect([1, 2, 3])->reverse()->values()->all(); // [3, 2, 1]
```

### `zip(iterable ...$items): Collection`
```php
collect([1, 2])->zip([3, 4])->first()->all(); // [1, 3]
```

---

## Set Operations

Implementation: [`Concerns/Collection/SetOperations.php`](../../core/libraries/Support/Concerns/Collection/SetOperations.php).

### `merge(iterable $items): Collection` / `mergeRecursive(iterable $items): Collection`
```php
collect([1, 2])->merge([3, 4])->all(); // [1, 2, 3, 4]
```

### `concat(iterable $source): Collection`
Append numerically (no key collision).
```php
collect([1, 2])->concat([3])->all(); // [1, 2, 3]
```

### `combine(iterable $values): Collection`
Use this collection's values as keys, combine with `$values`.
```php
collect(['a', 'b'])->combine([1, 2])->all(); // ['a' => 1, 'b' => 2]
```

### `diff(iterable $items): Collection` / `diffKeys(iterable $items): Collection`
```php
collect([1, 2, 3])->diff([2])->values()->all(); // [1, 3]
```

### `intersect(iterable $items): Collection` / `intersectByKeys(iterable $items): Collection`
```php
collect([1, 2, 3])->intersect([2, 3, 4])->values()->all(); // [2, 3]
```

---

## Sorting

Implementation: [`Concerns/Collection/Sorting.php`](../../core/libraries/Support/Concerns/Collection/Sorting.php).

### `sort(?callable $cb = null): Collection`
```php
collect([3, 1, 2])->sort()->values()->all(); // [1, 2, 3]
```

### `sortBy(callable|string $cb, int $options = SORT_REGULAR, bool $descending = false): Collection`
```php
collect([['n' => 3], ['n' => 1]])
    ->sortBy('n')
    ->values()
    ->pluck('n')
    ->all(); // [1, 3]
```

### `sortByDesc(callable|string $cb, int $options = SORT_REGULAR): Collection`
```php
collect([['n' => 1], ['n' => 3]])->sortByDesc('n')->values()->pluck('n')->all(); // [3, 1]
```

### `sortDesc(): Collection`
Without a key/callback.
```php
collect([1, 3, 2])->sortDesc()->values()->all(); // [3, 2, 1]
```

### `sortKeys(int $options = SORT_REGULAR, bool $descending = false): Collection`
```php
collect(['b' => 1, 'a' => 2])->sortKeys()->keys()->all(); // ['a', 'b']
```

### `unique(callable|string|null $key = null, bool $strict = false): Collection`
```php
collect([1, 2, 2, 3])->unique()->values()->all(); // [1, 2, 3]
collect([['t' => 'a'], ['t' => 'a'], ['t' => 'b']])->unique('t')->count(); // 2
```

### `duplicates(): Collection`
Items that appear more than once.
```php
collect([1, 2, 2, 3, 3])->duplicates()->count(); // 4
```

### `shuffle(?int $seed = null): Collection`
Seedable.
```php
collect([1, 2, 3])->shuffle()->count(); // 3
```

---

## Aggregates

Implementation: [`Concerns/Collection/Aggregates.php`](../../core/libraries/Support/Concerns/Collection/Aggregates.php).

### `sum(callable|string|null $cb = null): int|float`
```php
collect([1, 2, 3])->sum();                          // 6
collect([['n' => 1], ['n' => 2]])->sum('n');        // 3
```

### `avg(callable|string|null $cb = null): int|float|null` (alias `average`)
```php
collect([1, 2, 3, 4])->avg(); // 2.5
```

### `min(callable|string|null $cb = null): mixed` / `max(...)`
```php
collect([3, 1, 2])->min(); // 1
collect([3, 1, 2])->max(); // 3
```

### `median(callable|string|null $cb = null): int|float|null`
```php
collect([1, 2, 3])->median();    // 2
collect([1, 2, 3, 4])->median(); // 2.5
```

### `implode(string $glue, ?string $key = null): string`
```php
collect(['a', 'b', 'c'])->implode(',');                  // 'a,b,c'
collect([['n' => 'a'], ['n' => 'b']])->implode(',', 'n'); // 'a,b'
```

### `join(string $glue, string $finalGlue = ''): string`
```php
collect(['a', 'b', 'c'])->join(', ', ' and '); // 'a, b and c'
```

---

## Conditional

Implementation: [`Concerns/Collection/Conditional.php`](../../core/libraries/Support/Concerns/Collection/Conditional.php).

### `pipe(callable $cb): mixed`
Forward the Collection through a callback.
```php
collect([1, 2, 3])->pipe(fn ($c) => $c->sum()); // 6
```

### `tap(callable $cb): self`
Side effect; returns the Collection.
```php
collect([1, 2])->tap(fn ($c) => logger("count={$c->count()}"))->count(); // 2
```

### `when(mixed $cond, callable $cb, ?callable $default = null): self`
Run `$cb` only when `$cond` is truthy.
```php
collect([1, 2])->when(true, fn ($c) => $c->push(3))->all(); // [1, 2, 3]
```

### `unless(mixed $cond, callable $cb, ?callable $default = null): self`
```php
collect([1])->unless(false, fn ($c) => $c->push(2))->all(); // [1, 2]
```

### `whenEmpty(callable $cb, ?callable $default = null): self` / `whenNotEmpty(...)`
```php
collect([])->whenEmpty(fn ($c) => $c->push(1))->all();    // [1]
collect([1])->whenNotEmpty(fn ($c) => $c->push(2))->all(); // [1, 2]
```
