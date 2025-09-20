<?php

declare(strict_types=1);

namespace Zero\Lib\Validation;

interface RuleInterface
{
    /**
     * Unique identifier for the rule used when resolving custom messages.
     */
    public function name(): string;

    /**
     * Validate the given value. Return true when it passes.
     */
    public function passes(string $attribute, mixed $value, array $data): bool;

    /**
     * Default validation message (supports :attribute style placeholders).
     */
    public function message(): string;

    /**
     * Extra placeholder replacements exposed to the message resolver.
     *
     * @return array<string, string|int|float>
     */
    public function replacements(string $attribute, mixed $value, array $data): array;
}

