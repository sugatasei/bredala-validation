<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class NumberFilter extends Filter
{
    /**
     * Number validation
     *
     * @param mixed $value
     * @param string $message
     * @return float|null
     */
    public static function sanitize($value, string $message = 'type')
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if (!is_numeric($value)) {
            throw new ValidationException($message);
        }

        return (float) $value;
    }

    /**
     * @param float|int $value
     * @param float|int $num
     * @param string $message
     * @return float|int
     */
    public static function equal($value, $num, string $message = 'equal')
    {
        if ($value != $num) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param float|int $value
     * @param float|int $min
     * @param string $message
     * @return float|int
     */
    public static function min($value, $min, string $message = 'min')
    {
        if ($value < $min) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param float|int $value
     * @param float|int $max
     * @param string $message
     * @return float|int
     */
    public static function max($value, $min, string $message = 'max')
    {
        if ($value > $min) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param float|int $value
     * @param float|int $min
     * @param float|int $max
     * @param string $message
     * @return float|int
     */
    public static function range($value, $min, $max, string $message = 'range')
    {
        self::min($value, $min, $message);
        self::max($value, $max, $message);

        return $value;
    }
}
