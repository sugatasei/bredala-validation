<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class IntFilter extends NumberFilter
{
    /**
     * Integer validation
     *
     * @param mixed $value
     * @param string $message
     * @return integer|null
     */
    public static function sanitize($value, string $message = 'type')
    {
        $value = parent::sanitize($value, $message);

        $int_val = (int) $value;

        if ($value - $int_val) {
            throw new ValidationException($message);
        }

        return $int_val;
    }
}
