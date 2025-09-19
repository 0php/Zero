<?php

declare(strict_types=1);

namespace Zero\Lib\Http;

use Zero\Lib\Http\Concerns\InteractsWithCookies;
use Zero\Lib\Http\Concerns\InteractsWithHeaders;
use Zero\Lib\Http\Concerns\InteractsWithJson;
use Zero\Lib\Http\Concerns\InteractsWithServer;
use Zero\Lib\Http\Concerns\InteractsWithSession;

class Request
{
    use InteractsWithServer;
    use InteractsWithHeaders;
    use InteractsWithCookies;
    use InteractsWithJson;
    use InteractsWithSession;
}
