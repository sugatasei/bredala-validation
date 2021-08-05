<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

/**
 * Boolean
 */
class BoolFilter extends Filter
{
    /**
     * Boolean validation
     *
     * @param mixed $value
     * @param string $message
     * @return boolean|null
     */
    public static function sanitize($value, string $message = 'type')
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return !!$value;
        }

        if (is_string($value)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) return $value;
        }

        throw new ValidationException($message);
    }
}
