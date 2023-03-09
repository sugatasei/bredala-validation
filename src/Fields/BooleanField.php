<?php

namespace Bredala\Validation\Fields;

class BooleanField extends Field
{
    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public static function sanitize(mixed $value): ?int
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

            self::trigger('type');
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

        self::trigger('type');
    }

    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    public static function isTrue(bool $value)
    {
        return self::match($value, true);
    }

    public static function isFalse(bool $value)
    {
        return self::match($value, false);
    }

    // -------------------------------------------------------------------------
}
