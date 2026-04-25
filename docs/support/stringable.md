# Stringable (Fluent strings)

Fluent string wrapper. Namespace `Zero\Lib\Support\Stringable`. Build with `Str::of($value)` or the global `str($value)` helper.

```php
use Zero\Lib\Support\Str;

Str::of('users.profile-photo')
    ->replaceLast('.', '/')
    ->slug('/');             // 'users/profile-photo'

str('Hello {name}')
    ->swap(['{name}' => 'World'])
    ->upper();               // 'HELLO WORLD'
```

`Stringable::__toString()` returns the underlying string, so it casts cleanly: `(string) $stringable`.

## Auto-proxy to `Str`

Stringable forwards every static `Str::*` call automatically: anything that takes a string as its first argument and returns a string is chainable. So all of these work:

```php
str('foo')->upper();                  // FOO
str('Hello World')->slug();           // hello-world
str('a/b/c')->after('/');             // 'b/c'
str('foo bar')->replace('foo', 'XX'); // 'XX bar'
```

Methods that return non-strings (`->isUuid()`, `->length()`, `->test()`, …) return that value directly.

## Fluent-only methods

Implementation: [`Stringable.php`](../../core/libraries/Support/Stringable.php).

### Composition

#### `->append(...$values): self` / `->prepend(...$values): self`
```php
(string) str('foo')->append('bar', 'baz'); // 'foobarbaz'
(string) str('bar')->prepend('foo');       // 'foobar'
```

#### `->newLine(int $count = 1): self`
```php
(string) str('a')->newLine(2); // "a\n\n"
```

#### `->basename(string $suffix = ''): self`
```php
(string) str('/a/b/c.txt')->basename();         // 'c.txt'
(string) str('/a/b/c.txt')->basename('.txt');   // 'c'
```

#### `->dirname(int $levels = 1): self`
```php
(string) str('/a/b/c.txt')->dirname();      // '/a/b'
(string) str('/a/b/c.txt')->dirname(2);     // '/a'
```

#### `->classBasename(): self`
Last segment of a fully qualified class name.
```php
(string) str('Foo\\Bar\\Baz')->classBasename(); // 'Baz'
```

#### `->stripTags(?string $allowed = null): self`
```php
(string) str('<b>x</b>')->stripTags();          // 'x'
(string) str('<b>x</b><i>y</i>')->stripTags('<b>'); // '<b>x</b>y'
```

#### `->hash(string $algorithm = 'sha256'): self`
```php
(string) str('x')->hash();       // 64-char SHA-256
(string) str('x')->hash('md5');  // '9dd4e461268c8034f5c8564e155c67a6'
```

### Inspection (return non-self)

#### `->exactly(string $value): bool`
```php
str('hello')->exactly('hello'); // true
```

#### `->isEmpty(): bool` / `->isNotEmpty(): bool`
```php
str('')->isEmpty();  // true
str('x')->isNotEmpty(); // true
```

#### `->test(string $pattern): bool`
```php
str('hello')->test('/^h/'); // true
```

#### `->explode(string $delimiter, int $limit = PHP_INT_MAX): array`
```php
str('a,b,c')->explode(','); // ['a', 'b', 'c']
```

#### `->split(string $pattern, int $limit = -1, int $flags = 0): array`
Regex split.
```php
str('a1b2c')->split('/\d/'); // ['a', 'b', 'c']
```

#### `->scan(string $format): array`
`sscanf` shortcut.
```php
str('SN/2020/01')->scan('SN/%d/%d'); // [2020, 1]
```

### Conditional chains

These run `$cb` only when the predicate matches; otherwise `$default` (if given). Each returns the (possibly modified) Stringable.

#### `->when(mixed $cond, callable $cb, ?callable $default = null): self` / `->unless(...)`
`$cond` may be a callable, in which case its return value is checked.
```php
(string) str('foo')->when(true, fn ($s) => $s->upper());   // 'FOO'
(string) str('foo')->when(false, fn ($s) => $s->upper());  // 'foo'
(string) str('foo')->unless(false, fn ($s) => $s->upper()); // 'FOO'
```

#### `->whenEmpty($cb)` / `->whenNotEmpty($cb)`
```php
(string) str('')->whenEmpty(fn ($s) => $s->append('x'));    // 'x'
(string) str('x')->whenNotEmpty(fn ($s) => $s->append('y')); // 'xy'
```

#### `->whenContains($needles, $cb)`
```php
(string) str('hello')->whenContains('ell', fn ($s) => $s->upper()); // 'HELLO'
```

#### `->whenStartsWith($needles, $cb)` / `->whenEndsWith($needles, $cb)`
```php
(string) str('hello')->whenStartsWith('he', fn ($s) => $s->upper()); // 'HELLO'
(string) str('hello')->whenEndsWith('lo', fn ($s) => $s->upper());   // 'HELLO'
```

#### `->whenExactly($value, $cb)` / `->whenNotExactly($value, $cb)`
```php
(string) str('foo')->whenExactly('foo', fn ($s) => $s->upper()); // 'FOO'
```

#### `->whenIs($pattern, $cb)`
Wildcard match (`Str::is`).
```php
(string) str('foobar')->whenIs('foo*', fn ($s) => $s->upper()); // 'FOOBAR'
```

#### `->whenIsAscii($cb)` / `->whenIsUuid($cb)` / `->whenIsUlid($cb)`
```php
(string) str('hi')->whenIsAscii(fn ($s) => $s->upper()); // 'HI'
```

#### `->whenTest('/regex/', $cb)`
```php
(string) str('hi5')->whenTest('/\d/', fn ($s) => $s->upper()); // 'HI5'
```

### Pipeline

#### `->pipe(callable $cb): mixed`
Send through a closure (return whatever you want).
```php
str('queue')->pipe(fn ($s) => (string) $s . ':default'); // 'queue:default'
```

#### `->tap(callable $cb): self`
Side effect; returns the Stringable.
```php
(string) str('foo')->tap(fn ($s) => logger($s)); // 'foo'
```
