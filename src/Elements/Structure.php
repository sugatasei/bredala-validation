<?php

namespace Bredala\Validation\Elements;

use Bredala\Validation\Filters\ArrayFilter;
use Bredala\Validation\Result;
use Bredala\Validation\Schema;

/**
 * An array with known keys, each validated by its own schema.
 * Unknown keys are dropped; an absent structure is processed as [].
 */
class Structure extends Schema
{
    /**
     * @param Schema[] $items
     */
    public function __construct(private array $items)
    {
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
            if (!$callback($values)) {
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
