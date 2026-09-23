<?php

namespace Bredala\Validation\Elements;

use Bredala\Validation\Schema;

/**
 * A scalar value, sanitized by a function of Bredala\Validation\Filters.
 */
class Type extends Schema
{
    /** @var callable|null */
    private mixed $sanitizer;

    public function __construct(?callable $sanitizer = null)
    {
        $this->sanitizer = $sanitizer;
    }

    protected function sanitize(mixed $value): mixed
    {
        return $this->sanitizer ? ($this->sanitizer)($value) : $value;
    }
}
