# Str

Static string helpers. Namespace `Zero\Lib\Support\Str` (aliased globally as `Str`).

```php
use Zero\Lib\Support\Str;
```

Topics: [Transforms](#transforms) · [Search](#search) · [Extraction](#extraction) · [Replacement](#replacement) · [Composition](#composition) · [Identity](#identity) · [Encoding](#encoding) · [Pluralization](#pluralization) · [Casing](#casing) · [Padding](#padding) · [Random](#random) · [Fluent](#fluent)

---

## Transforms

Implementation: [`Concerns/Str/Transforms.php`](../../core/libraries/Support/Concerns/Str/Transforms.php).

### `studly(string $value): string`
StudlyCase the value (also called PascalCase).
```php
Str::studly('make_http_client'); // 'MakeHttpClient'
```

### `snake(string $value): string`
snake_case the value.
```php
Str::snake('MakeHTTPClient'); // 'make_http_client'
```

### `kebab(string $value): string`
kebab-case the value.
```php
Str::kebab('MakeHTTPClient'); // 'make-http-client'
```

### `camel(string $value): string`
camelCase the value.
```php
Str::camel('make_http_client'); // 'makeHttpClient'
```

### `title(string $value): string`
Title Case Each Word.
```php
Str::title('make http client'); // 'Make Http Client'
```

### `upper(string $value): string` / `lower(string $value): string`
Standard case conversion.
```php
Str::upper('Zero'); // 'ZERO'
Str::lower('Zero'); // 'zero'
```

### `slug(string $value, string $separator = '-'): string`
URL-friendly slug, with ASCII transliteration.
```php
Str::slug('Héllø Wörld'); // 'hello-world'
Str::slug('A B C', '_');  // 'a_b_c'
```

### `ascii(string $value, string $fallback = '?'): string`
Transliterate to ASCII; non-mappable bytes become `$fallback`.
```php
Str::ascii('déjà vu');         // 'deja vu'
Str::ascii('🚀 launch', '');    // ' launch'
```

### `transliterate(string $string, string $unknown = '?', bool $strict = false): string`
Alias for `ascii()` with Laravel-compatible signature.
```php
Str::transliterate('héllo'); // 'hello'
```

---

## Search

Implementation: [`Concerns/Str/Search.php`](../../core/libraries/Support/Concerns/Str/Search.php).

### `contains(string $haystack, string $needle): bool`
```php
Str::contains('queue:email', 'email'); // true
```

### `containsAll(string $haystack, iterable $needles): bool`
```php
Str::containsAll('queue:email', ['queue', 'email']); // true
```

### `containsAny(string $haystack, iterable $needles): bool`
```php
Str::containsAny('queue:email', ['http', 'email']); // true
```

### `startsWith(string $haystack, string $needle): bool` / `endsWith(...)`
```php
Str::startsWith('cache:foo', 'cache:'); // true
Str::endsWith('image.png', '.png');      // true
```

### `startsWithAny(...)` / `endsWithAny(...)`
```php
Str::startsWithAny('cache:foo', ['queue:', 'cache:']);          // true
Str::endsWithAny('a.tar.gz', ['.zip', '.tar.gz']);              // true
```

### `doesntContain($haystack, $needles, $ignoreCase = false): bool`
```php
Str::doesntContain('hello world', 'foo'); // true
```

### `doesntStartWith(...)` / `doesntEndWith(...)`
```php
Str::doesntStartWith('hello', 'world'); // true
Str::doesntEndWith('hello', '!');       // true
```

### `position(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false`
Multibyte `mb_strpos`.
```php
Str::position('hello', 'l'); // 2
```

### `substrCount(string $haystack, string $needle, int $offset = 0, ?int $length = null): int`
```php
Str::substrCount('aaa', 'a'); // 3
```

---

## Extraction

Implementation: [`Concerns/Str/Extraction.php`](../../core/libraries/Support/Concerns/Str/Extraction.php).

### `limit(string $value, int $limit, string $end = '...'): string`
```php
Str::limit('A very long sentence', 10); // 'A very...'
```

### `words(string $value, int $words, string $end = '...'): string`
```php
Str::words('one two three four', 2); // 'one two...'
```

### `limitWords(...)` — alias of `words()`.

### `substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string`
```php
Str::substr('framework', 0, 5); // 'frame'
```

### `after(string $subject, string $search): string` / `before(...)`
```php
Str::after('auth:token', ':');  // 'token'
Str::before('auth:token', ':'); // 'auth'
```

### `between(string $subject, string $from, string $to): string`
```php
Str::between('[42]', '[', ']'); // '42'
```

### `afterLast(...)` / `beforeLast(...)`
```php
Str::afterLast('a/b/c', '/');  // 'c'
Str::beforeLast('a/b/c', '/'); // 'a/b'
```

### `betweenFirst(string $subject, string $from, string $to): string`
```php
Str::betweenFirst('[a][b]', '[', ']'); // 'a'
```

### `charAt(string $subject, int $index): string|false`
Multibyte-safe index access. Negative indexes count from the end.
```php
Str::charAt('hello', 1);  // 'e'
Str::charAt('hello', -1); // 'o'
Str::charAt('hello', 99); // false
```

### `take(string $string, int $limit): string`
First or last `n` characters. Negative `$limit` returns from the tail.
```php
Str::take('hello', 3);  // 'hel'
Str::take('hello', -3); // 'llo'
```

### `excerpt(string $text, string $phrase = '', array $options = []): ?string`
Excerpt a phrase out of a longer text, surrounded by omission marks. `radius` (default 100) sets the window.
```php
Str::excerpt('hello world', 'world', ['radius' => 3]); // '...lo world'
```

### `match(string $pattern, string $subject): string`
First regex group (or full match if no groups).
```php
Str::match('/foo (\w+)/', 'foo bar'); // 'bar'
```

### `matchAll(string $pattern, string $subject): array`
All regex matches (group 1 if present, else full match).
```php
Str::matchAll('/\d+/', 'a 1 b 2 c 3'); // ['1','2','3']
```

---

## Replacement

Implementation: [`Concerns/Str/Replacement.php`](../../core/libraries/Support/Concerns/Str/Replacement.php).

### `replaceFirst(string $search, string $replace, string $subject): string`
```php
Str::replaceFirst('zero', 'one', 'zero zero'); // 'one zero'
```

### `replaceLast(...)`
```php
Str::replaceLast('zero', 'one', 'zero zero'); // 'zero one'
```

### `replace($search, $replace, $subject, $caseSensitive = true): string|array`
Standard `str_replace` (or `str_ireplace`).
```php
Str::replace('foo', 'bar', 'foobaz'); // 'barbaz'
```

### `replaceArray(string $search, array $replace, string $subject): string`
Replace each occurrence sequentially.
```php
Str::replaceArray('?', ['a','b'], '? and ?'); // 'a and b'
```

### `replaceStart(string $search, string $replace, string $subject): string`
Replace only when `$subject` starts with `$search`.
```php
Str::replaceStart('foo', 'X', 'foobar'); // 'Xbar'
Str::replaceStart('foo', 'X', 'barbar'); // 'barbar'
```

### `replaceEnd(string $search, string $replace, string $subject): string`
```php
Str::replaceEnd('bar', 'X', 'foobar'); // 'fooX'
```

### `replaceMatches(string $pattern, string|callable $replace, string $subject, int $limit = -1): string`
Regex replace; `$replace` may be a callable.
```php
Str::replaceMatches('/\d+/', 'X', 'a 1 b 2'); // 'a X b X'
```

### `swap(array $map, string $subject): string`
```php
Str::swap(['{name}' => 'Zero'], 'Hello {name}'); // 'Hello Zero'
```

### `remove($search, string $subject, bool $caseSensitive = true): string`
```php
Str::remove('-', 'a-b-c'); // 'abc'
```

### `substrReplace(string $string, string $replace, int $offset = 0, ?int $length = null): string`
```php
Str::substrReplace('hello', 'X', 1, 1); // 'hXllo'
```

---

## Composition

Implementation: [`Concerns/Str/Composition.php`](../../core/libraries/Support/Concerns/Str/Composition.php).

### `start(string $value, string $prefix): string`
Ensure the value starts with `$prefix` (collapsing duplicates).
```php
Str::start('foo', '/');     // '/foo'
Str::start('//foo', '/');   // '/foo'
```

### `finish(string $value, string $cap): string`
```php
Str::finish('foo', '/');    // 'foo/'
Str::finish('foo//', '/');  // 'foo/'
```

### `ensureSuffix(string $value, string $suffix): string`
Append the suffix only when missing (no duplicate collapse).
```php
Str::ensureSuffix('storage/logs', '/'); // 'storage/logs/'
```

### `wrap(string $value, string $before, ?string $after = null): string`
```php
Str::wrap('hello', '"');                 // '"hello"'
Str::wrap('hello', '<b>', '</b>');       // '<b>hello</b>'
```

### `unwrap(string $value, string $before, ?string $after = null): string`
```php
Str::unwrap('"hello"', '"'); // 'hello'
```

### `reverse(string $value): string`
Multibyte-safe reverse.
```php
Str::reverse('hello'); // 'olleh'
```

### `squish(string $value): string`
Collapse all whitespace runs to single spaces and trim.
```php
Str::squish("  a   b\t c "); // 'a b c'
```

### `deduplicate(string $value, string $character = ' '): string`
```php
Str::deduplicate('a   b'); // 'a b'
```

### `chopStart(string $subject, string|array $needle): string`
Remove the prefix when present.
```php
Str::chopStart('foobar', 'foo');                // 'bar'
Str::chopStart('foobar', ['baz', 'foo']);       // 'bar'
```

### `chopEnd(string $subject, string|array $needle): string`
```php
Str::chopEnd('foobar', 'bar'); // 'foo'
```

### `trim(string $value, ?string $charlist = null): string` / `ltrim(...)` / `rtrim(...)`
```php
Str::trim('  x  ');  // 'x'
Str::ltrim('  x  '); // 'x  '
Str::rtrim('  x  '); // '  x'
```

### `headline(string $value): string`
Word-boundary-aware Title Case (handles dashes, underscores, and StudlyCase).
```php
Str::headline('a-pretty_title'); // 'A Pretty Title'
```

### `apa(string $value): string`
APA title casing (lower-cases minor words mid-title).
```php
Str::apa('the quick brown fox'); // 'The Quick Brown Fox'
```

### `initials(string $value, string $glue = ''): string`
```php
Str::initials('John Doe');       // 'JD'
Str::initials('John Doe', '.');  // 'J.D'
```

### `mask(string $value, string $character, int $index, ?int $length = null, string $encoding = 'UTF-8'): string`
Mask a portion of the value.
```php
Str::mask('1234567890', '*', 4);    // '1234******'
Str::mask('1234567890', '*', 4, 4); // '1234****90'
```

---

## Identity

Implementation: [`Concerns/Str/Identity.php`](../../core/libraries/Support/Concerns/Str/Identity.php).

### `is(string|iterable $pattern, string $value): bool`
Wildcard match (`*`).
```php
Str::is('foo*', 'foobar');           // true
Str::is(['admin/*', 'api/*'], 'api/users'); // true
```

### `isAscii(string $value): bool`
```php
Str::isAscii('hello');  // true
Str::isAscii('héllo');  // false
```

### `isJson(string $value): bool`
```php
Str::isJson('{"a":1}'); // true
Str::isJson('not json'); // false
```

### `isUrl(string $value, array $protocols = []): bool`
```php
Str::isUrl('https://example.com');               // true
Str::isUrl('https://x', ['ftp']);                // false (scheme not allowed)
```

### `isUuid(string $value): bool`
```php
Str::isUuid('550e8400-e29b-41d4-a716-446655440000'); // true
```

### `isUlid(string $value): bool`
```php
Str::isUlid('01HW2YPK6Z5XZK7B5N8R7F0Q1V'); // true
```

### `isMatch(string|array $pattern, string $value): bool`
Regex match (any pattern in the list).
```php
Str::isMatch('/^foo/', 'foobar'); // true
```

---

## Encoding

Implementation: [`Concerns/Str/Encoding.php`](../../core/libraries/Support/Concerns/Str/Encoding.php).

### `toBase64(string $value): string`
```php
Str::toBase64('hello'); // 'aGVsbG8='
```

### `fromBase64(string $value, bool $strict = false): string`
Returns `''` on decode failure.
```php
Str::fromBase64('aGVsbG8='); // 'hello'
```

---

## Pluralization

Naive English. Implementation: [`Concerns/Str/Pluralization.php`](../../core/libraries/Support/Concerns/Str/Pluralization.php).

### `plural(string $value, int|array|Countable $count = 2): string`
```php
Str::plural('apple');         // 'apples'
Str::plural('apple', 1);      // 'apple'
Str::plural('city');          // 'cities'
Str::plural('bush');          // 'bushes'
```

### `singular(string $value): string`
```php
Str::singular('cities'); // 'city'
Str::singular('boxes');  // 'box'
Str::singular('apples'); // 'apple'
```

### `pluralStudly(string $value, int|array|Countable $count = 2): string`
Pluralize the last StudlyCase segment.
```php
Str::pluralStudly('UserPost'); // 'UserPosts'
```

---

## Casing

Implementation: [`Concerns/Str/Casing.php`](../../core/libraries/Support/Concerns/Str/Casing.php).

### `lcfirst(string $value): string` / `ucfirst(string $value): string`
Multibyte-safe.
```php
Str::lcfirst('Hello'); // 'hello'
Str::ucfirst('hello'); // 'Hello'
```

### `ucwords(string $value, string $delimiters = " \t\r\n\f\v"): string`
```php
Str::ucwords('hello world'); // 'Hello World'
```

### `ucsplit(string $value): array`
Split on uppercase boundaries.
```php
Str::ucsplit('FooBarBaz'); // ['Foo', 'Bar', 'Baz']
```

### `length(string $value, ?string $encoding = null): int`
Multibyte-safe length.
```php
Str::length('ありがとう'); // 5
```

### `wordCount(string $string, ?string $characters = null): int`
```php
Str::wordCount('a b c'); // 3
```

### `wordWrap(string $string, int $characters = 75, string $break = "\n", bool $cutLongWords = false): string`
```php
echo Str::wordWrap('the quick brown fox', 10, "\n", true);
// "the quick\nbrown fox"
```

---

## Padding

Implementation: [`Concerns/Str/Padding.php`](../../core/libraries/Support/Concerns/Str/Padding.php).

### `padLeft(string $value, int $length, string $pad = ' '): string`
```php
Str::padLeft('7', 3, '0'); // '007'
```

### `padRight(string $value, int $length, string $pad = ' '): string`
```php
Str::padRight('7', 3, '0'); // '700'
```

### `padBoth(string $value, int $length, string $pad = ' '): string`
```php
Str::padBoth('core', 8, '-'); // '--core--'
```

### `repeat(string $value, int $times): string`
```php
Str::repeat('-', 5); // '-----'
```

---

## Random

Implementation: [`Concerns/Str/Random.php`](../../core/libraries/Support/Concerns/Str/Random.php).

### `uuid(): string`
RFC4122 v4 UUID.
```php
Str::uuid(); // 'd3b07384-d9a3-4d2c-9f7e-...'
```

### `uuid7(?DateTimeInterface $time = null): string` / `orderedUuid(): string`
Time-ordered UUIDv7. `orderedUuid()` is an alias for `uuid7()` with no args.
```php
Str::uuid7();        // '01928a...'
Str::orderedUuid();  // '01928a...'
```

### `ulid(): string`
Time-ordered Crockford ULID.
```php
Str::ulid(); // '01HW2YPK6Z5XZK7B5N8R7F0Q1V'
```

### `random(int $length = 16, ?string $alphabet = null): string`
Cryptographically random token. Default alphabet is base62.
```php
Str::random(16);                       // 'k9QzXk7...'
Str::random(8, '0123456789');          // '04823917'
```

### `password(int $length = 32, bool $letters = true, bool $numbers = true, bool $symbols = true, bool $spaces = false): string`
Strong random password.
```php
Str::password(20); // 'Xk9!aZ.fQ$cP|7yL@vRm'
```

---

## Fluent

Implementation: [`Concerns/Str/Fluent.php`](../../core/libraries/Support/Concerns/Str/Fluent.php).

### `of(string $value): Stringable`
Begin a fluent chain. See [stringable.md](stringable.md).
```php
Str::of('users.profile-photo')
    ->replaceLast('.', '/')
    ->slug('/'); // 'users/profile-photo'
```

The global `str($value)` is shorthand for `Str::of($value)`.
