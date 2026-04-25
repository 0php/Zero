<?php

declare(strict_types=1);

namespace Zero\Lib\Support;

use Zero\Lib\Support\Concerns\Arr\Access;
use Zero\Lib\Support\Concerns\Arr\Internal;
use Zero\Lib\Support\Concerns\Arr\Iteration;
use Zero\Lib\Support\Concerns\Arr\Shape;
use Zero\Lib\Support\Concerns\Arr\Sorting;
use Zero\Lib\Support\Concerns\Arr\Tests;

/**
 * Static array helpers, composed from topical traits under
 * Zero\Lib\Support\Concerns\Arr\*. The public API is unchanged from the
 * monolithic version — every Arr::* call you used before keeps working.
 *
 * @see docs/support/arr.md
 */
final class Arr
{
    use Internal;
    use Access;
    use Iteration;
    use Shape;
    use Sorting;
    use Tests;
}
