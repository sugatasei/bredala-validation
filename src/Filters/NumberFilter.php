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
    public static function sanitize(mixed $value, string $message = 'type'): ?float
    {
        if ($value === null) {
            return null;
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

        throw new ValidationException($message);
    }

    /**
     * @param float $value
     * @param float $min
     */
    public static function min(float $value, float $min): void
    {
        if ($value < $min) {
            throw new ValidationException('min');
        }
    }

    /**
     * @param float $value
     * @param float $max
     */
    public static function max(float $value, float $max): void
    {
        if ($value > $max) {
            throw new ValidationException('max');
        }
    }

    /**
     * @param float $value
     * @param float $min
     * @param float $max
     */
    public static function range(float $value, float $min, float $max): void
    {
        if ($value < $min) {
            throw new ValidationException('range');
        }
        if ($value > $max) {
            throw new ValidationException('range');
        }
    }
}
