<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\Schema;

class ArrayFilter
{
    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public static function sanitize(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value ?: null;
        }

        if ($value === null || is_string($value) && trim($value) === '') {
            return null;
        }

        Schema::fail('type');
    }

    public static function map(mixed $value, callable $callback): ?array
    {
        if (!($values = self::sanitize($value))) {
            return $values;
        }

        foreach ($values as $k => $v) {
            $values[$k] = $callback($v);
        }

        return $values;
    }

    public static function mapArray(mixed $value): ?array
    {
        return self::map($value, [ArrayFilter::class, 'sanitize']);
    }

    public static function mapBoolean(mixed $value): ?array
    {
        return self::map($value, [BooleanFilter::class, 'sanitize']);
    }

    public static function mapInteger(mixed $value): ?array
    {
        return self::map($value, [IntegerFilter::class, 'sanitize']);
    }

    public static function mapDecimal(mixed $value): ?array
    {
        return self::map($value, [DecimalFilter::class, 'sanitize']);
    }

    public static function mapInput(mixed $value): ?array
    {
        return self::map($value, [StringFilter::class, 'input']);
    }

    public static function mapText(mixed $value): ?array
    {
        return self::map($value, [StringFilter::class, 'text']);
    }

    // -------------------------------------------------------------------------
}
