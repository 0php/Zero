# Date & Time Helpers

Lightweight helpers for date operations, plus the global `now()` / `today()` shortcuts.

```php
use Zero\Lib\Support\DateTime;
```

Implementation: [`DateTime.php`](../core/libraries/Support/DateTime.php), legacy [`Date.php`](../core/libraries/Support/Date.php).

---

## `DateTime` (immutable, preferred)

Built on `DateTimeImmutable` — every mutation returns a new instance.

### `DateTime::now(?DateTimeZone $tz = null): self`
```php
$now = DateTime::now();
$jakarta = DateTime::now(new DateTimeZone('Asia/Jakarta'));
```

### `DateTime::parse(string $datetime, ?DateTimeZone $tz = null): self`
Parses any string supported by PHP's `DateTimeImmutable` — relative ("yesterday", "next monday") or absolute ("2026-04-25 12:00").
```php
$cutoff   = DateTime::parse('2026-04-25');
$tomorrow = DateTime::parse('tomorrow');
$paris    = DateTime::parse('today 12:00', new DateTimeZone('Europe/Paris'));
```

### `->addDays(int $days): self`
```php
$deadline = DateTime::now()->addDays(3);
```

### `->subDays(int $days): self`
```php
$weekAgo = DateTime::now()->subDays(7);
```

### `->diffForHumans(DateTimeInterface $other): string`
Human-readable difference, e.g. `"2 hours ago"`, `"5 days from now"`.
```php
echo DateTime::now()->addDays(3)->diffForHumans(DateTime::now()); // "3 days from now"
```

### `->inTimeZone(string|DateTimeZone $tz): self`
Return a clone converted to another timezone.
```php
$utc = DateTime::now()->inTimeZone('UTC');
```

`DateTime` extends `DateTimeImmutable`, so PHP's standard methods (`format`, `getTimestamp`, etc.) all work:
```php
DateTime::now()->format('Y-m-d H:i:s');
DateTime::now()->getTimestamp();
```

---

## `Date` (legacy mutable wrapper)

Kept for backwards compatibility. New code should prefer `DateTime`. The framework also exposes `Zero\Lib\Date` as an alias of this class.

### `Date::now(?DateTimeZone $tz = null): self`
```php
$now = Date::now();
```

### `Date::parse(string $time, ?DateTimeZone $tz = null): self`
```php
$date = Date::parse('next friday');
```

### `->addDays(int $days): self`
```php
$next = Date::now()->addDays(7);
```

### `->subtractDays(int $days): self`
Note the legacy spelling — `subtractDays`, not `subDays`.
```php
$before = Date::now()->subtractDays(7);
```

### `->format(string $format = DateTime::ATOM): string`
```php
Date::now()->format('Y-m-d');     // '2026-04-25'
Date::now()->format();            // ATOM, e.g. '2026-04-25T12:34:56+07:00'
```

### `->toDateTime(): \DateTime`
Drop down to the native PHP `\DateTime` instance.
```php
$native = Date::now()->toDateTime();
```

### `->diffForHumans(self $other): string`
```php
$past = Date::parse('yesterday');
echo Date::now()->diffForHumans($past); // "1 day from now"
```

### `->setTimeZone(string|DateTimeZone $tz): self`
```php
$jakarta = Date::now()->setTimeZone('Asia/Jakarta');
```

---

## Global helpers

Defined in [`Support/Helper.php`](../core/libraries/Support/Helper.php).

### `now(): Date`
```php
$now = now();              // Date instance
$tomorrow = now()->addDays(1);
```

### `today(): Date`
Date instance for the start of today.
```php
$today = today();
```

See [helpers.md](helpers.md) for the full list of global helpers.
