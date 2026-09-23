<?php

namespace Bredala\Validation\Rules;

class NumberRule
{
    public static function min(int|float $value, int|float $min): bool
    {
        return $value >= $min;
    }

    public static function max(int|float $value, int|float $max): bool
    {
        return $value <= $max;
    }

    /**
     * $value is a multiple of $step: 0.3 is a multiple of 0.1 despite float
     * rounding.
     */
    public static function isMultipleOf(int|float $value, int|float $step): bool
    {
        if (is_int($value) && is_int($step)) {
            return $step !== 0 && $value % $step === 0;
        }

        if ($step == 0) {
            return false;
        }

        $ratio = $value / $step;

        return abs($ratio - round($ratio)) < 1e-9;
    }
}
