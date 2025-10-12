<?php

declare(strict_types=1);

namespace Zero\Lib;

if (! class_exists(__NAMESPACE__ . '\Date', false)) {
    class_alias(\Zero\Lib\Support\Date::class, __NAMESPACE__ . '\Date');
}
