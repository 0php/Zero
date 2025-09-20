<?php

declare(strict_types=1);

namespace Zero\Lib\Http;

use Zero\Lib\Http\Concerns\InteractsWithCookies;
use Zero\Lib\Http\Concerns\InteractsWithHeaders;
use Zero\Lib\Http\Concerns\InteractsWithJson;
use Zero\Lib\Http\Concerns\InteractsWithServer;
use Zero\Lib\Http\Concerns\InteractsWithSession;
use Zero\Lib\Validation\ValidationException;
use Zero\Lib\Validation\Validator;

class Request
{
    use InteractsWithServer;
    use InteractsWithHeaders;
    use InteractsWithCookies;
    use InteractsWithJson;
    use InteractsWithSession;

    /**
     * Validate the request input using the given rules.
     *
     * @param array<string, array<int, string|callable|\Zero\Lib\Validation\RuleInterface>|string> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        return Validator::make($this->all(), $rules, $messages, $attributes)->validate();
    }
}
