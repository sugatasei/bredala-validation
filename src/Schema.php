<?php

namespace Bredala\Validation;

use BackedEnum;
use InvalidArgumentException;

/**
 * Pipeline of a schema element:
 * before() -> sanitize -> null (required / default) -> rules -> assert()
 * -> values() ... then, when valid: transform() -> castTo() -> data().
 */
abstract class Schema
{
    use SchemaFactory;

    protected bool $required = false;
    protected mixed $default = null;
    private array $before = [];
    private array $rules = [];
    protected array $asserts = [];
    private array $transforms = [];
    private ?string $castTo = null;

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;
        return $this;
    }

    /**
     * Runs on the raw input, before the type's own sanitizing.
     */
    public function before(callable $callback): static
    {
        $this->before[] = $callback;
        return $this;
    }

    /**
     * Runs on the sanitized value. A falsy return sets $code; the callback can
     * also throw its own code with Schema::fail().
     */
    public function assert(callable $callback, string $code = 'assert'): static
    {
        $this->asserts[] = [$callback, $code];
        return $this;
    }

    /**
     * Runs on the valid value, for data() only: values() is left untouched.
     * The callback can reject the value with Schema::fail().
     */
    public function transform(callable $callback): static
    {
        $this->transforms[] = $callback;
        return $this;
    }

    /**
     * Type of the value in data(): 'int', 'float', 'string', 'bool', 'array',
     * 'object', a backed enum (Enum::tryFrom()) or a class (new Class($value)).
     */
    public function castTo(string $type): static
    {
        if (!in_array($type, ['int', 'float', 'string', 'bool', 'array', 'object'], true)) {
            if (!class_exists($type) && !enum_exists($type)) {
                throw new InvalidArgumentException("Unknown cast type '{$type}'");
            }
            if (enum_exists($type) && !is_subclass_of($type, BackedEnum::class)) {
                throw new InvalidArgumentException("Enum '{$type}' must be a backed enum");
            }
        }

        $this->castTo = $type;
        return $this;
    }

    /**
     * A built-in check, run before the assert() callbacks: a falsy return of
     * $check sets $code. Declaring a code again replaces its check.
     */
    protected function rule(string $code, callable $check): static
    {
        $this->rules[$code] = $check;
        return $this;
    }

    /**
     * Rejects the value with an error code, from a before(), assert() or
     * transform() callback.
     */
    public static function fail(string $code): never
    {
        throw new ValidationException($code);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function validate(mixed $input): Result
    {
        try {
            foreach ($this->before as $callback) {
                $input = $callback($input);
            }
            $value = $this->sanitize($input);
        } catch (ValidationException $ex) {
            return new Result($this->emptyValue(), $ex->getMessage());
        }

        if ($value === null) {
            return $this->processNull();
        }

        return $this->processValue($value);
    }

    // -------------------------------------------------------------------------
    // Processing
    // -------------------------------------------------------------------------

    /**
     * The value shown in values() when the input could not be sanitized.
     */
    protected function emptyValue(): mixed
    {
        return $this->default;
    }

    protected function sanitize(mixed $value): mixed
    {
        return $value;
    }

    protected function processNull(): Result
    {
        if ($this->required) {
            return new Result($this->default, 'required');
        }

        return $this->complete($this->default, $this->default);
    }

    protected function processValue(mixed $value): Result
    {
        try {
            $this->check($value);
            $this->runAsserts($value);
        } catch (ValidationException $ex) {
            return new Result($value, $ex->getMessage());
        }

        return $this->complete($value, $value);
    }

    /**
     * Runs the rules, throwing the code of the first one that fails.
     */
    protected function check(mixed $value): void
    {
        foreach ($this->rules as $code => $check) {
            if (!$check($value)) {
                self::fail($code);
            }
        }
    }

    protected function runAsserts(mixed $value): void
    {
        foreach ($this->asserts as [$callback, $code]) {
            if (!$callback($value)) {
                self::fail($code);
            }
        }
    }

    /**
     * Builds the data() side of a valid value: transform() then castTo().
     */
    protected function complete(mixed $value, mixed $data): Result
    {
        try {
            foreach ($this->transforms as $callback) {
                if ($data === null) {
                    break;
                }
                $data = $callback($data);
            }

            if ($data !== null && $this->castTo !== null) {
                $data = $this->cast($data, $this->castTo);
            }
        } catch (ValidationException $ex) {
            return new Result($value, $ex->getMessage());
        }

        return new Result($value, null, $data);
    }

    private function cast(mixed $data, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $data,
            'float' => (float) $data,
            'string' => (string) $data,
            'bool' => (bool) $data,
            'array' => (array) $data,
            'object' => (object) $data,
            default => is_subclass_of($type, BackedEnum::class)
                ? ($type::tryFrom($data) ?? self::fail('type'))
                : $this->instantiate($type, $data),
        };
    }

    protected function instantiate(string $class, mixed $data): object
    {
        return new $class($data);
    }

    // -------------------------------------------------------------------------
}
