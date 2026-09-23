<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\Schema;

class IntegerFilter
{
    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public static function sanitize(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        Schema::fail('type');
    }

    // -------------------------------------------------------------------------
}
