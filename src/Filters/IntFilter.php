<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class IntFilter extends Filter
{
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

    public static function min(?int $value, int $min): ?int
    {
        if ($value === null) {
            return $value;
        }

        if ($value < $min) {
            throw new ValidationException('min');
        }

        return $value;
    }

    public static function max(?int $value, int $max): ?int
    {
        if ($value === null) {
            return $value;
        }

        if ($value > $max) {
            throw new ValidationException('max');
        }

        return $value;
    }

    public static function range(?int $value, int $min, int $max): ?int
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
