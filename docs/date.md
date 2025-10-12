# Date & Time Helpers

Zero Framework bundles lightweight helpers for immutable date operations. They build on PHP's `DateTimeImmutable` but expose a cleaner API and chainable syntax.

## `Zero\Lib\Support\DateTime`

```php
use Zero\Lib\Support\DateTime;

$now = DateTime::now();                    // current timestamp (immutable)
$deadline = $now->addDays(3);              // clone with 3 days added
$reminder = $deadline->inTimeZone('UTC');  // convert to another timezone

echo $deadline->diffForHumans($now);       // "3 days from now"
```

### Key methods

| Method | Description |
| --- | --- |
| `DateTime::now(?DateTimeZone $tz = null)` | Current timestamp as an immutable instance. |
| `DateTime::parse(string $value, ?DateTimeZone $tz = null)` | Parse relative or absolute time strings. |
| `addDays(int $days)` / `subDays(int $days)` | Clone the instance with the given number of days added/subtracted. |
| `diffForHumans(DateTimeInterface $other)` | Human readable difference (e.g. `"2 hours ago"`, `"5 days from now"`). |
| `inTimeZone(string|DateTimeZone $tz)` | Return a clone converted to the given timezone. |

The helper returns new instances for every mutation, so you never accidentally modify the original timestamp.

### Converting to native `DateTime`

You can pass the support class anywhere a native `DateTimeInterface` is expected. If you need a mutable version, call `DateTime::parse()` and use PHP's native `DateTime` APIs on the returned string representation.

## Legacy `Zero\Lib\Support\Date`

The legacy `Date` wrapper remains available for backwards compatibility. It uses the new support `DateTime` internally but keeps the old mutable API:

```php
use Zero\Lib\Support\Date;

$date = Date::now()
    ->addDays(2)
    ->setTimeZone('Asia/Jakarta');

$human = $date->diffForHumans(Date::parse('yesterday'));
```

Prefer the immutable helper for new code, but both classes interoperate seamlessly (you can call `Date::toDateTime()` to retrieve the underlying immutable instance).
