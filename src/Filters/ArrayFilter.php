<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\Helper;
use Bredala\Validation\ValidationException;

class ArrayFilter extends Filter
{
    /**
     * Array validation
     *
     * @param mixed $value
     * @param string $message
     * @return array
     */
    public static function sanitize($value, string $message = 'type')
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new ValidationException($message);
        }

        return Helper::sanitizeValue($value);
    }

    /**
     * @param array $value
     * @param integer $num
     * @param string $message
     * @return array
     */
    public static function equal(array $value, int $num, string $message = 'equal'): array
    {
        if (count($value) !== $num) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param array $value
     * @param integer $min
     * @param string $message
     * @return array
     */
    public static function min(array $value, int $min, string $message = 'min'): array
    {
        if (count($value) < $min) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param array $value
     * @param integer $max
     * @param string $message
     * @return array
     */
    public static function max(array $value, int $min, string $message = 'max'): array
    {
        if (count($value) > $min) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param array $value
     * @param integer $min
     * @param integer $max
     * @param string $message
     * @return array
     */
    public static function range(array $value, int $min, int $max, string $message = 'range'): array
    {
        self::min($value, $min, $message);
        self::max($value, $max, $message);

        return $value;
    }
}
