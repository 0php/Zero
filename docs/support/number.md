# Number

Numeric formatting helpers. Namespace `Zero\Lib\Support\Number` (aliased as `Number`).

```php
use Zero\Lib\Support\Number;
```

When the `intl` extension is available, locale-aware formatting via `NumberFormatter` is used; otherwise a native PHP fallback applies.

Implementation: [`Number.php`](../../core/libraries/Support/Number.php).

---

### `format(int|float $number, int $precision = 0, ?int $maxPrecision = null, ?string $locale = null): string`
```php
Number::format(1234567);        // '1,234,567'
Number::format(1234.5678, 2);   // '1,234.57'
Number::format(1234.5, 0, 2);   // '1,234.5'
```

### `spell(int|float $number, ?string $locale = null): string`
Spell out the number. Requires `intl`.
```php
Number::spell(5);  // 'five'
Number::spell(42); // 'forty-two'
```

### `ordinal(int $number, ?string $locale = null): string`
```php
Number::ordinal(1);  // '1st'
Number::ordinal(2);  // '2nd'
Number::ordinal(3);  // '3rd'
Number::ordinal(11); // '11th'
Number::ordinal(22); // '22nd'
```

### `percentage(int|float $number, int $precision = 0, ?int $maxPrecision = null, ?string $locale = null): string`
```php
Number::percentage(50);          // '50%'
Number::percentage(33.5, 1);     // '33.5%'
```

### `currency(int|float $number, string $currency = 'USD', ?string $locale = null): string`
Locale-dependent formatting.
```php
Number::currency(1234.56, 'USD');               // '$1,234.56'
Number::currency(1234.56, 'EUR', 'de_DE');      // '1.234,56 €'
```

### `fileSize(int|float $bytes, int $precision = 0, ?int $maxPrecision = null): string`
```php
Number::fileSize(500);                       // '500 B'
Number::fileSize(2048);                      // '2 KB'
Number::fileSize(1024 * 1024 * 1.5, 1, 1);   // '1.5 MB'
```

### `forHumans(int|float $number, int $precision = 0, ?int $maxPrecision = null, bool $abbreviate = false): string`
Human-readable order of magnitude.
```php
Number::forHumans(1500);        // '2 thousand'
Number::forHumans(1500000);     // '2 million'
Number::forHumans(1500, 1, 1);  // '1.5 thousand'
```

### `abbreviate(int|float $number, int $precision = 0, ?int $maxPrecision = null): string`
Abbreviated suffix variant.
```php
Number::abbreviate(1500);           // '2K'
Number::abbreviate(1500, 1, 1);     // '1.5K'
Number::abbreviate(2_500_000);      // '3M'
```

### `pairs(int|float $to, int|float $by, int|float $offset = 1): array`
Build numeric ranges in chunks.
```php
Number::pairs(10, 5);       // [[1, 5], [6, 10]]
Number::pairs(25, 10);      // [[1, 10], [11, 20], [21, 25]]
```

### `trim(int|float $number): int|float`
Strip trailing zeros from a float.
```php
Number::trim(5);     // 5
Number::trim(5.10);  // 5.1
Number::trim(5.00);  // 5
```

### `clamp(int|float $number, int|float $min, int|float $max): int|float`
```php
Number::clamp(15, 1, 10);  // 10
Number::clamp(-5, 0, 10);  // 0
Number::clamp(5, 0, 10);   // 5
```
