<?php

namespace Bredala\Validation\Elements;

use Bredala\Validation\Filters\ArrayFilter;
use Bredala\Validation\Result;
use Bredala\Validation\Schema;
use Bredala\Validation\ValidationException;

/**
 * An array whose elements are all validated by the same schema.
 * Keys are preserved; an absent array defaults to [].
 */
class ArrayOf extends Schema
{
    private ?int $min = null;
    private ?int $max = null;

    public function __construct(
        private Schema $item,
        private ?Schema $key = null,
        private bool $list = false,
    ) {
        $this->default = [];
    }

    public function min(int $min): static
    {
        $this->min = $min;
        return $this;
    }

    public function max(int $max): static
    {
        $this->max = $max;
        return $this;
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

    protected function check(mixed $value): void
    {
        if ($this->list && !array_is_list($value)) {
            Schema::fail('list');
        }

        if ($this->min !== null && count($value) < $this->min) {
            Schema::fail('min');
        }

        if ($this->max !== null && count($value) > $this->max) {
            Schema::fail('max');
        }
    }

    protected function processNull(): Result
    {
        if ($this->required) {
            return new Result([], 'required');
        }

        if ($this->min) {
            return new Result([], 'min');
        }

        return $this->complete($this->default, $this->default);
    }
}
