<?php

namespace Bredala\Validation\Elements;

use Bredala\Validation\Filters\ArrayFilter;
use Bredala\Validation\Result;
use Bredala\Validation\Schema;
use Bredala\Validation\ValidationException;

/**
 * An array with known keys, each validated by its own schema.
 * Unknown keys are dropped; an absent structure is processed as [].
 */
class Structure extends Schema
{
    /**
     * @param Schema[] $items
     */
    private bool $optional = false;

    public function __construct(private array $items)
    {
    }

    /**
     * Makes the structure optional: when absent, it takes this value instead
     * of processing its items.
     */
    public function default(mixed $value): static
    {
        $this->optional = true;
        return parent::default($value);
    }

    /**
     * Runs on the sanitized values of the structure, once all its items are
     * valid. The error goes on $field when given, otherwise on the structure.
     */
    public function assert(callable $callback, string $code = 'assert', ?string $field = null): static
    {
        $this->asserts[] = [$callback, $code, $field];
        return $this;
    }

    /**
     * $field must equal $other, like a password confirmation. The error
     * goes on $field.
     */
    public function same(string $field, string $other): static
    {
        return $this->assert(fn(array $v) => $v[$field] === $v[$other], 'same', $field);
    }

    /**
     * $field must differ from $other. The error goes on $field.
     */
    public function different(string $field, string $other): static
    {
        return $this->assert(fn(array $v) => $v[$field] !== $v[$other], 'different', $field);
    }

    /**
     * @return Schema[]
     */
    public function items(): array
    {
        return $this->items;
    }

    // -------------------------------------------------------------------------
    // Processing
    // -------------------------------------------------------------------------

    protected function emptyValue(): mixed
    {
        return array_map(fn(Schema $schema) => $schema->validate(null)->values(), $this->items);
    }

    protected function sanitize(mixed $value): mixed
    {
        return ArrayFilter::sanitize($value);
    }

    protected function processNull(): Result
    {
        if ($this->optional) {
            return $this->complete($this->default, $this->default);
        }

        return $this->processValue([]);
    }

    protected function processValue(mixed $value): Result
    {
        $values = [];
        $errors = [];
        $data = [];

        foreach ($this->items as $name => $schema) {
            $result = $schema->validate($value[$name] ?? null);
            $values[$name] = $result->values();
            $data[$name] = $result->isValid() ? $result->data() : null;
            if (!$result->isValid()) {
                $errors[$name] = $result->error();
            }
        }

        if ($errors) {
            return new Result($values, $errors);
        }

        foreach ($this->asserts as [$callback, $code, $field]) {
            try {
                $valid = $callback($values);
            } catch (ValidationException $ex) {
                [$valid, $code] = [false, $ex->getMessage()];
            }
            if (!$valid) {
                return new Result($values, $field === null ? $code : [$field => $code]);
            }
        }

        return $this->complete($values, $data);
    }

    /**
     * A class with a constructor gets the data as named arguments, otherwise
     * the data is assigned to its properties.
     */
    protected function instantiate(string $class, mixed $data): object
    {
        if (method_exists($class, '__construct')) {
            return new $class(...$data);
        }

        $object = new $class();
        foreach ($data as $name => $value) {
            $object->{$name} = $value;
        }

        return $object;
    }
}
