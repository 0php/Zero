# Support Utilities

Zero ships a family of dependency-free helpers under `Zero\Lib\Support`. Each one is documented on its own page with a runnable example for every method.

| Topic | Class / namespace | Doc |
| --- | --- | --- |
| Strings | `Zero\Lib\Support\Str` | [str.md](str.md) |
| Fluent strings | `Zero\Lib\Support\Stringable` (via `Str::of()` / `str()`) | [stringable.md](stringable.md) |
| Arrays | `Zero\Lib\Support\Arr` | [arr.md](arr.md) |
| Collections | `Zero\Lib\Support\Collection` (via `collect()`) | [collection.md](collection.md) |
| Numbers | `Zero\Lib\Support\Number` | [number.md](number.md) |
| Dates | `Zero\Lib\Support\Date` / `DateTime` | [../date.md](../date.md) |
| HTTP client | `Zero\Lib\Http` | [http.md](http.md) |
| SOAP client | `Http::soap()` | [soap.md](soap.md) |
| Filesystem | `Zero\Lib\Filesystem\File` | [filesystem.md](filesystem.md) |
| Global helpers | `core/libraries/Support/Helper.php` | [../helpers.md](../helpers.md) |

## Code organization

`Str`, `Arr`, and `Collection` are composed of topical traits under `core/libraries/Support/Concerns/<Class>/<Topic>.php`. The public class names and FQCNs are unchanged — `use Zero\Lib\Support\Str;` etc. work exactly as before.

The trait split mirrors the doc topic split:

- **`Str`** → [Transforms](str.md#transforms), [Search](str.md#search), [Extraction](str.md#extraction), [Replacement](str.md#replacement), [Composition](str.md#composition), [Identity](str.md#identity), [Encoding](str.md#encoding), [Pluralization](str.md#pluralization), [Casing](str.md#casing), [Padding](str.md#padding), [Random](str.md#random), [Fluent](str.md#fluent)
- **`Arr`** → [Access](arr.md#access), [Iteration](arr.md#iteration), [Shape](arr.md#shape), [Sorting](arr.md#sorting), [Tests](arr.md#tests)
- **`Collection`** → [Building](collection.md#building), [Conversion](collection.md#conversion), [Iteration](collection.md#iteration), [Filtering](collection.md#filtering), [Querying](collection.md#querying), [Mutation](collection.md#mutation), [Slicing](collection.md#slicing), [Reshaping](collection.md#reshaping), [Set Operations](collection.md#set-operations), [Sorting](collection.md#sorting), [Aggregates](collection.md#aggregates), [Conditional](collection.md#conditional)
