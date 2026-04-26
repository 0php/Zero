<?php

declare(strict_types=1);

namespace Zero\Lib\Support;

use Zero\Lib\Support\Concerns\Str\Casing;
use Zero\Lib\Support\Concerns\Str\Composition;
use Zero\Lib\Support\Concerns\Str\Encoding;
use Zero\Lib\Support\Concerns\Str\Extraction;
use Zero\Lib\Support\Concerns\Str\Fluent;
use Zero\Lib\Support\Concerns\Str\Identity;
use Zero\Lib\Support\Concerns\Str\Padding;
use Zero\Lib\Support\Concerns\Str\Pluralization;
use Zero\Lib\Support\Concerns\Str\Random;
use Zero\Lib\Support\Concerns\Str\Replacement;
use Zero\Lib\Support\Concerns\Str\Search;
use Zero\Lib\Support\Concerns\Str\Transforms;

/**
 * Static string helpers, composed from topical traits under
 * Zero\Lib\Support\Concerns\Str\*. The public API is unchanged from the
 * monolithic version — every Str::* call you used before keeps working.
 *
 * @see docs/support/str.md
 */
final class Str
{
    use Transforms;
    use Search;
    use Extraction;
    use Replacement;
    use Composition;
    use Identity;
    use Encoding;
    use Pluralization;
    use Casing;
    use Padding;
    use Random;
    use Fluent;
}
