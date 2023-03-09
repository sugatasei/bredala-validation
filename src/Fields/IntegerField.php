<?php

namespace Bredala\Validation\Fields;

class IntegerField extends Field
{
    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

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

        self::trigger('type');
    }

    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    public static function min(int $value, int $min)
    {
        if ($value < $min) {
            self::trigger('min');
        }
    }

    public static function max(int $value, int $max)
    {
        if ($value > $max) {
            self::trigger('max');
        }
    }

    public static function range(int $value, int $min, int $max)
    {
        if ($value < $min) {
            self::trigger('range');
        }

        if ($value > $max) {
            self::trigger('range');
        }
    }

    // -------------------------------------------------------------------------
}
