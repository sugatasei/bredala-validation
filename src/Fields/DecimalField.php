<?php

namespace Bredala\Validation\Fields;

class DecimalField extends Field
{
    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public static function sanitize(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            return $value;
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

        self::trigger('type');
    }

    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    public static function min(float $value, float $min)
    {
        if ($value < $min) {
            self::trigger('min');
        }
    }

    public static function max(float $value, float $max)
    {
        if ($value > $max) {
            self::trigger('max');
        }
    }

    public static function range(float $value, float $min, float $max)
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
