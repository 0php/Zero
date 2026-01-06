<?php

declare(strict_types=1);

namespace Zero\Lib\Validation;

use Closure;
use InvalidArgumentException;
use Zero\Lib\Validation\Rules\ArrayRule;
use Zero\Lib\Validation\Rules\BooleanRule;
use Zero\Lib\Validation\Rules\Confirmed;
use Zero\Lib\Validation\Rules\Email;
use Zero\Lib\Validation\Rules\Exists;
use Zero\Lib\Validation\Rules\FileRule;
use Zero\Lib\Validation\Rules\Image;
use Zero\Lib\Validation\Rules\Max;
use Zero\Lib\Validation\Rules\Min;
use Zero\Lib\Validation\Rules\MimeTypes;
use Zero\Lib\Validation\Rules\Mimes;
use Zero\Lib\Validation\Rules\Number;
use Zero\Lib\Validation\Rules\Password;
use Zero\Lib\Validation\Rules\Required;
use Zero\Lib\Validation\Rules\StringRule;
use Zero\Lib\Validation\Rules\Unique;

final class Validator
{
    private array $errors = [];
    private bool $validated = false;
    private array $validatedData = [];

    /**
     * @var array<string, array<int, RuleInterface|callable|string>>
     */
    private array $parsedRules = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<int, string>|string> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $messages = [],
        private array $attributes = []
    ) {
        $this->parsedRules = $this->prepareRules($rules);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<int, string>|string> $rules
     */
    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): self
    {
        return new self($data, $rules, $messages, $attributes);
    }

    /**
     * Run the validation and return validated data or throw.
     *
     * @throws ValidationException
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this);
        }

        return $this->validated();
    }

    public function fails(): bool
    {
        if (!$this->validated) {
            $this->runValidation();
        }

        return !empty($this->errors);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        $this->fails();

        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $this->fails();

        return $this->validatedData;
    }

    private function runValidation(): void
    {
        foreach ($this->parsedRules as $attribute => $rules) {
            $value = $this->dataGet($this->data, $attribute);
            $failed = false;

            foreach ($rules as $rule) {
                if ($rule instanceof RuleInterface) {
                    if (!$rule->passes($attribute, $value, $this->data)) {
                        $this->addError($attribute, $rule->name(), $this->resolveMessage($attribute, $rule, $value));
                        $failed = true;
                        break;
                    }

                    continue;
                }

                if ($rule instanceof Closure || is_callable($rule)) {
                    $result = $rule($attribute, $value, $this->data);

                    if ($result === false) {
                        $this->addError($attribute, 'closure', $this->resolveCustomClosureMessage($attribute));
                        $failed = true;
                        break;
                    }

                    continue;
                }

                throw new InvalidArgumentException('Invalid validation rule supplied.');
            }

            if (!$failed) {
                $this->setValidatedValue($attribute, $value);
            }
        }

        $this->validated = true;
    }

    private function addError(string $attribute, string $rule, string $message): void
    {
        $this->errors[$attribute][] = $message;
    }

    private function resolveMessage(string $attribute, RuleInterface $rule, mixed $value): string
    {
        $keyed = $attribute . '.' . $rule->name();

        if (isset($this->messages[$keyed])) {
            return $this->replacePlaceholders($attribute, $this->messages[$keyed], $rule, $value);
        }

        if (isset($this->messages[$rule->name()])) {
            return $this->replacePlaceholders($attribute, $this->messages[$rule->name()], $rule, $value);
        }

        return $this->replacePlaceholders($attribute, $rule->message(), $rule, $value);
    }

    private function resolveCustomClosureMessage(string $attribute): string
    {
        $default = 'The :attribute field is invalid.';

        if (isset($this->messages[$attribute])) {
            $default = $this->messages[$attribute];
        }

        return str_replace(':attribute', $this->attributeName($attribute), $default);
    }

    private function replacePlaceholders(string $attribute, string $message, RuleInterface $rule, mixed $value): string
    {
        $replacements = array_merge(
            [
                ':attribute' => $this->attributeName($attribute),
                ':value' => is_scalar($value) ? (string) $value : '',
            ],
            $this->formatReplacements($rule, $attribute, $value)
        );

        return strtr($message, $replacements);
    }

    private function formatReplacements(RuleInterface $rule, string $attribute, mixed $value): array
    {
        $replacements = [];

        foreach ($rule->replacements($attribute, $value, $this->data) as $key => $replacement) {
            $replacements[':' . $key] = is_scalar($replacement) ? (string) $replacement : '';
        }

        return $replacements;
    }

    private function attributeName(string $attribute): string
    {
        if (isset($this->attributes[$attribute])) {
            return $this->attributes[$attribute];
        }

        return str_replace(['_', '.'], [' ', ' '], $attribute);
    }

    private function setValidatedValue(string $attribute, mixed $value): void
    {
        $segments = explode('.', $attribute);
        $target =& $this->validatedData;

        foreach ($segments as $segment) {
            if (!is_array($target)) {
                $target = [];
            }

            if (!array_key_exists($segment, $target)) {
                $target[$segment] = [];
            }

            $target =& $target[$segment];
        }

        $target = $value;
    }

    private function dataGet(array $target, string $key): mixed
    {
        if ($key === '' || $key === null) {
            return $target;
        }

        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } else {
                return null;
            }
        }

        return $target;
    }

    /**
     * @param array<string, array<int, string>|string> $rules
     * @return array<string, array<int, RuleInterface|callable>>
     */
    private function prepareRules(array $rules): array
    {
        $prepared = [];

        foreach ($rules as $attribute => $ruleSet) {
            if (is_string($ruleSet)) {
                $ruleSet = $ruleSet === '' ? [] : explode('|', $ruleSet);
            }

            $prepared[$attribute] = [];

            foreach ($ruleSet as $rule) {
                if ($rule instanceof RuleInterface || $rule instanceof Closure || is_callable($rule)) {
                    $prepared[$attribute][] = $rule;

                    continue;
                }

                if (!is_string($rule)) {
                    throw new InvalidArgumentException('Validation rules must be strings, callables, or RuleInterface implementations.');
                }

                $prepared[$attribute][] = $this->resolveStringRule($rule);
            }
        }

        return $prepared;
    }

    private function resolveStringRule(string $rule): RuleInterface
    {
        [$name, $parameters] = $this->parseRuleString($rule);

        return match ($name) {
            'required' => new Required(),
            'string' => new StringRule(),
            'email' => new Email(),
            'min' => new Min($this->extractNumericParameter($name, $parameters)),
            'max' => new Max($this->extractNumericParameter($name, $parameters)),
            'confirmed' => new Confirmed(),
            'boolean' => new BooleanRule(),
            'array' => new ArrayRule(),
            'number' => new Number(),
            'file' => new FileRule(),
            'image' => new Image(),
            'mimes' => $this->buildMimesRule($parameters),
            'mimetypes' => $this->buildMimeTypesRule($parameters),
            'unique' => $this->buildUniqueRule($parameters),
            'exists' => $this->buildExistsRule($parameters),
            'password' => $this->buildPasswordRule($parameters),
            default => throw new InvalidArgumentException("Unknown validation rule [{$name}]."),
        };
    }

    private function extractNumericParameter(string $rule, array $parameters): float
    {
        if (!isset($parameters[0]) || $parameters[0] === '') {
            throw new InvalidArgumentException(sprintf('Validation rule [%s] expects a numeric parameter.', $rule));
        }

        if (!is_numeric($parameters[0])) {
            throw new InvalidArgumentException(sprintf('Validation rule [%s] received a non-numeric parameter.', $rule));
        }

        return (float) $parameters[0];
    }

    private function buildUniqueRule(array $parameters): Unique
    {
        $table = $this->extractTableParameter('unique', $parameters);
        $column = $this->nullableParameter($parameters[1] ?? null);
        $ignore = $this->nullableParameter($parameters[2] ?? null);
        $idColumn = $this->nullableParameter($parameters[3] ?? null);

        return new Unique($table, $column, $ignore, $idColumn);
    }

    private function buildExistsRule(array $parameters): Exists
    {
        $table = $this->extractTableParameter('exists', $parameters);
        $column = $this->nullableParameter($parameters[1] ?? null);

        return new Exists($table, $column);
    }

    private function buildPasswordRule(array $parameters): Password
    {
        $requirements = [];

        if (isset($parameters[0]) && $parameters[0] !== '') {
            $requirements = array_map('trim', explode(',', $parameters[0]));
        }

        return new Password($requirements);
    }

    private function buildMimesRule(array $parameters): Mimes
    {
        $extensions = $this->extractStringListParameter('mimes', $parameters);

        return new Mimes($extensions);
    }

    private function buildMimeTypesRule(array $parameters): MimeTypes
    {
        $types = $this->extractStringListParameter('mimetypes', $parameters);

        return new MimeTypes($types);
    }

    /**
     * @return array<int, string>
     */
    private function extractStringListParameter(string $rule, array $parameters): array
    {
        if ($parameters === []) {
            throw new InvalidArgumentException(sprintf('Validation rule [%s] expects at least one parameter.', $rule));
        }

        $normalized = [];

        foreach ($parameters as $parameter) {
            $value = strtolower(trim((string) $parameter));

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        $normalized = array_values(array_unique($normalized));

        if ($normalized === []) {
            throw new InvalidArgumentException(sprintf('Validation rule [%s] requires non-empty parameters.', $rule));
        }

        return $normalized;
    }

    private function extractTableParameter(string $rule, array $parameters): string
    {
        $value = $parameters[0] ?? null;

        if ($value === null || $value === '') {
            throw new InvalidArgumentException(sprintf('Validation rule [%s] expects a table name as the first parameter.', $rule));
        }

        return $value;
    }

    private function nullableParameter(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function parseRuleString(string $rule): array
    {
        $segments = explode(':', $rule, 2);
        $name = strtolower(trim($segments[0]));
        $parameters = [];

        if (isset($segments[1])) {
            $parameters = array_map('trim', explode(',', $segments[1]));
        }

        return [$name, $parameters];
    }
}
