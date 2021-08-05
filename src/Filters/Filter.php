<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class Filter
{
    /**
     * @param mixed $value
     * @param string $message
     * @return mixed
     */
    public static function required($value, string $message = 'required')
    {
        if (empty($value)) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @param array $items
     * @param string $message
     */
    public static function include($value, array $items, $message = 'include')
    {
        if (in_array($value, $items)) {
            return $value;
        }

        throw new ValidationException($message);
    }

    /**
     * @param mixed $value
     * @param array $items
     * @param string $message
     */
    public static function exclude($value, array $items, $message = 'include')
    {
        if (!in_array($value, $items)) {
            return $value;
        }

        throw new ValidationException($message);
    }
}
