# Support Utilities

Zero ships a family of dependency-free helpers under `Zero\Lib\Support`. Each one has its own per-method reference page under [`docs/support/`](support/).

| Topic | Class / namespace | Doc |
| --- | --- | --- |
| Strings | `Zero\Lib\Support\Str` | [support/str.md](support/str.md) |
| Fluent strings | `Zero\Lib\Support\Stringable` | [support/stringable.md](support/stringable.md) |
| Arrays | `Zero\Lib\Support\Arr` | [support/arr.md](support/arr.md) |
| Collections | `Zero\Lib\Support\Collection` | [support/collection.md](support/collection.md) |
| Numbers | `Zero\Lib\Support\Number` | [support/number.md](support/number.md) |
| Dates | `Zero\Lib\Support\Date` / `DateTime` | [date.md](date.md) |
| HTTP client | `Zero\Lib\Http` | [support/http.md](support/http.md) |
| SOAP client | `Http::soap()` | [support/soap.md](support/soap.md) |
| Filesystem | `Zero\Lib\Filesystem\File` | [support/filesystem.md](support/filesystem.md) |
| Global helpers | `core/libraries/Support/Helper.php` | [helpers.md](helpers.md) |

## Code organization

`Str`, `Arr`, and `Collection` are composed of topical traits under [`core/libraries/Support/Concerns/<Class>/<Topic>.php`](../core/libraries/Support/Concerns/). The public class names and FQCNs are unchanged — `use Zero\Lib\Support\Str;` etc. work exactly as before, and every existing `Str::*`, `Arr::*`, and `Collection::*` method continues to function identically.

The trait split mirrors the doc topic split, so each per-topic doc page corresponds to one trait file:

- **`Str`** → 12 traits (Transforms, Search, Extraction, Replacement, Composition, Identity, Encoding, Pluralization, Casing, Padding, Random, Fluent)
- **`Arr`** → 5 traits (Access, Iteration, Shape, Sorting, Tests) plus an `Internal` trait for shared helpers
- **`Collection`** → 11 traits (Conversion, Iteration, Filtering, Querying, Mutation, Slicing, Reshaping, SetOperations, Sorting, Aggregates, Conditional)

Open the linked doc page for any class to see one example per method.
