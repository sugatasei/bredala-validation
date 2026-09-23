<?php

namespace Bredala\Validation\Rules;

class ArrayRule
{
    public static function minCount(array $value, int $min): bool
    {
        return count($value) >= $min;
    }

    public static function maxCount(array $value, int $max): bool
    {
        return count($value) <= $max;
    }

    /**
     * No two elements are equal (strictly, so 1 and '1' differ).
     */
    public static function isUnique(array $value): bool
    {
        $seen = [];
        foreach ($value as $item) {
            if (in_array($item, $seen, true)) {
                return false;
            }
            $seen[] = $item;
        }

        return true;
    }

    /**
     * Keys are 0, 1, 2...
     */
    public static function isList(array $value): bool
    {
        return array_is_list($value);
    }
}
