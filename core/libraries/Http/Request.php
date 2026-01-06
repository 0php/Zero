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
    use InteractsWithServer {
        InteractsWithServer::dataGet insteadof InteractsWithJson;
        InteractsWithServer::dataGet as protected serverDataGet;
    }
    use InteractsWithHeaders;
    use InteractsWithCookies;
    use InteractsWithJson {
        InteractsWithJson::dataGet as protected jsonDataGet;
    }
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
        return Validator::make($this->validationData(), $rules, $messages, $attributes)->validate();
    }

    /**
     * Merge input, JSON payload, and normalized files for validation.
     *
     * @return array<string, mixed>
     */
    protected function validationData(): array
    {
        return array_replace_recursive($this->all(), $this->files());
    }
}
