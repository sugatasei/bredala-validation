<?php

namespace Bredala\Validation;

use LogicException;

class Result
{
    /**
     * @internal Built by Schema::validate().
     */
    public function __construct(
        private mixed $values,
        private string|array|null $error = null,
        private mixed $data = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->error === null;
    }

    /**
     * The sanitized values, before transform() and castTo(), even when the data
     * is not valid: meant to fill a form back.
     */
    public function values(): mixed
    {
        return $this->values;
    }

    /**
     * Error codes shaped like the data. An error of the root element itself
     * is under the '' key.
     */
    public function errors(): array
    {
        if ($this->error === null) {
            return [];
        }

        return is_array($this->error) ? $this->error : ['' => $this->error];
    }

    /**
     * The error as is: the element's own code, the errors of its children, or null.
     */
    public function error(): string|array|null
    {
        return $this->error;
    }

    /**
     * The values after transform() and castTo().
     */
    public function data(): mixed
    {
        if (!$this->isValid()) {
            throw new LogicException('Cannot read the data of an invalid result');
        }

        return $this->data;
    }
}
