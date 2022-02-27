<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class NumberFilter extends Filter
{
    public static function sanitize(mixed $value): ?float
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

        throw new ValidationException('type');
    }

    public static function min(?float $value, float $min): ?float
    {
        if ($value === null) {
            return $value;
        }

        if ($value < $min) {
            throw new ValidationException('min');
        }

        return $value;
    }

    public static function max(?float $value, float $max): ?float
    {
        if ($value === null) {
            return $value;
        }

        if ($value > $max) {
            throw new ValidationException('max');
        }

        return $value;
    }

    public static function range(?float $value, float $min, float $max): ?float
    {
        if ($value === null) {
            return $value;
        }

        if ($value < $min) {
            throw new ValidationException('range');
        }

        if ($value > $max) {
            throw new ValidationException('range');
        }

        return $value;
    }
}
