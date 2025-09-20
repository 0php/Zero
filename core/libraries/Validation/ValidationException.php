<?php

declare(strict_types=1);

namespace Zero\Lib\Validation;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(private Validator $validator)
    {
        parent::__construct('The given data was invalid.', 422);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->validator->errors();
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validator->validated();
    }

    public function validator(): Validator
    {
        return $this->validator;
    }
}

