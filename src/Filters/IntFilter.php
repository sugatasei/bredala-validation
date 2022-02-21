<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class IntFilter extends Filter
{
    /**
     * Integer validation
     *
     * @param mixed $value
     * @return integer|null
     */
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

        throw new ValidationException('type');
    }

    /**
     * @param int $value
     * @param int $min
     */
    public static function min(int $value, int $min): void
    {
        if ($value < $min) {
            throw new ValidationException('min');
        }
    }

    /**
     * @param int $value
     * @param int $max
     */
    public static function max(int $value, int $max): void
    {
        if ($value > $max) {
            throw new ValidationException('max');
        }
    }

    /**
     * @param int $value
     * @param int $min
     * @param int $max
     */
    public static function range(int $value, int $min, int $max): void
    {
        if ($value < $min) {
            throw new ValidationException('range');
        }
        if ($value > $max) {
            throw new ValidationException('range');
        }
    }
}
