<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class ArrayFilter extends Filter
{
    public static function sanitizeInt(mixed $value): array
    {
        return self::parseArray(self::sanitizeArray($value), [IntFilter::class, 'sanitize']);
    }

    public static function sanitizeNumber(mixed $value): array
    {
        return self::parseArray(self::sanitizeArray($value), [NumberFilter::class, 'sanitize']);
    }

    public static function sanitizeText(mixed $value): array
    {
        return self::parseArray(self::sanitizeArray($value), [StringFilter::class, 'sanitize']);
    }

    public static function sanitizeObject(mixed $value): array
    {
        $input = self::sanitizeArray($value);
        $output = [];

        foreach ($input as $key => $value) {

            if (is_string($value)) {
                $value = json_decode($value, true);
            }

            if (is_array($value)) {
                $output[$key] = $value;
            }
        }

        return $output;
    }

    private static function sanitizeArray(mixed $value): array
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

    private static function parseArray(array $input, ?callable $callback = null)
    {
        $output = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $output[$key] = self::parseArray($value);
            } else {
                try {
                    $output[$key] = call_user_func($callback, $value);
                } catch (ValidationException $ex) {
                }
            }
        }

        return $output;
    }

    /**
     * @param array $value
     * @param integer $min
     */
    public static function min(array $value, int $min): void
    {
        if (count($value) < $min) {
            throw new ValidationException('min');
        }
    }

    /**
     * @param array $value
     * @param integer $max
     */
    public static function max(array $value, int $max): void
    {
        if (count($value) > $max) {
            throw new ValidationException('max');
        }
    }

    /**
     * @param array $value
     * @param integer $min
     * @param integer $max
     */
    public static function range(array $value, int $min, int $max): void
    {
        if (count($value) < $min) {
            throw new ValidationException('min');
        }

        if (count($value) > $max) {
            throw new ValidationException('max');
        }
    }
}
