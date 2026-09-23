<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\Schema;

class BooleanFilter
{
    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public static function sanitize(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            $value = (int) $value;
            if ($value === 0) {
                return false;
            } elseif ($value === 1) {
                return true;
            }

            Schema::fail('type');
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                return $value;
            }
        }

        Schema::fail('type');
    }

    // -------------------------------------------------------------------------
}
