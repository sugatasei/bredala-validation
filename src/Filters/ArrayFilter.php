<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class ArrayFilter extends Filter
{
    public static function sanitize(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            throw new ValidationException('type');
        }

        return $value;
    }

    public static function map(mixed $input, callable $callback)
    {
        if (!$input || !is_array($input)) {
            return [];
        }

        $output = [];
        foreach ($input as $key => $value) {
            try {
                $output[$key] = call_user_func($callback, $value);
            } catch (ValidationException $ex) {
            }
        }

        return $output;
    }

    public static function sanitizeObject(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            throw new ValidationException('type');
        }

        return $value;
    }

    public static function min(?array $value, int $min): array
    {
        $count = $value ? count($value) : 0;

        if (count($value) < $min) {
            throw new ValidationException('min');
        }

        return $value;
    }

    /**
     * @param array $value
     * @param integer $max
     */
    public static function max(?array $value, int $max): array
    {
        $count = $value ? count($value) : 0;

        if ($count > $max) {
            throw new ValidationException('max');
        }

        return $value;
    }

    /**
     * @param array $value
     * @param integer $min
     * @param integer $max
     */
    public static function range(?array $value, int $min, int $max): array
    {
        $count = $value ? count($value) : 0;

        if ($count < $min) {
            throw new ValidationException('min');
        }

        if ($count > $max) {
            throw new ValidationException('max');
        }

        return $value;
    }
}
