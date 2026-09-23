<?php

namespace Bredala\Validation\Elements;

use Bredala\Validation\Filters\ArrayFilter;
use Bredala\Validation\Result;
use Bredala\Validation\Rules\ArrayRule;
use Bredala\Validation\Schema;
use Bredala\Validation\ValidationException;

/**
 * An array whose elements are all validated by the same schema.
 * Keys are preserved; an absent array defaults to [].
 */
class ArrayOf extends Schema
{
    public function __construct(
        private Schema $item,
        private ?Schema $key = null,
        bool $list = false,
    ) {
        $this->default = [];

        if ($list) {
            $this->rule('list', fn(array $v) => ArrayRule::isList($v));
        }
    }

    /**
     * Minimum number of elements.
     */
    public function min(int $min): static
    {
        return $this->rule('min', fn(array $v) => ArrayRule::minCount($v, $min));
    }

    /**
     * Maximum number of elements.
     */
    public function max(int $max): static
    {
        return $this->rule('max', fn(array $v) => ArrayRule::maxCount($v, $max));
    }

    /**
     * No two elements are equal, compared on their sanitized values.
     * Runs like an assert(): only when all elements are valid.
     */
    public function unique(): static
    {
        return $this->assert(ArrayRule::isUnique(...), 'unique');
    }

    // -------------------------------------------------------------------------
    // Processing
    // -------------------------------------------------------------------------

    protected function emptyValue(): mixed
    {
        return [];
    }

    protected function sanitize(mixed $value): mixed
    {
        return ArrayFilter::sanitize($value);
    }

    protected function processValue(mixed $value): Result
    {
        $values = [];
        $errors = [];
        $data = [];

        foreach ($value as $k => $v) {
            $result = $this->item->validate($v);
            $values[$k] = $result->values();
            $data[$k] = $result->isValid() ? $result->data() : null;

            if ($this->key && !$this->key->validate($k)->isValid()) {
                $errors[$k] = 'key';
            } elseif (!$result->isValid()) {
                $errors[$k] = $result->error();
            }
        }

        try {
            $this->check($value);
        } catch (ValidationException $ex) {
            return new Result($values, $ex->getMessage());
        }

        if ($errors) {
            return new Result($values, $errors);
        }

        try {
            $this->runAsserts($values);
        } catch (ValidationException $ex) {
            return new Result($values, $ex->getMessage());
        }

        return $this->complete($values, $data);
    }

    /**
     * An absent array is its default ([]): its rules still apply, so min(1)
     * rejects it.
     */
    protected function processNull(): Result
    {
        if ($this->required) {
            return new Result([], 'required');
        }

        try {
            $this->check($this->default);
        } catch (ValidationException $ex) {
            return new Result($this->default, $ex->getMessage());
        }

        return $this->complete($this->default, $this->default);
    }
}
