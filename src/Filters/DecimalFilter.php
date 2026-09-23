<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\Schema;

class DecimalFilter
{
    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public static function sanitize(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        Schema::fail('type');
    }

    // -------------------------------------------------------------------------
}
