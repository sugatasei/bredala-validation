<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class Filter
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public static function required(mixed $value): void
    {
        if ($value === null || $value === []) {
            throw new ValidationException('required');
        }
    }

    /**
     * @param mixed $value
     * @param array $items
     */
    public static function include(mixed $value, array $items): void
    {
        if (!in_array($value, $items)) {
            throw new ValidationException('include');
        }
    }

    /**
     * @param mixed $value
     * @param array $items
     */
    public static function exclude(mixed $value, array $items): void
    {
        if (in_array($value, $items)) {
            throw new ValidationException('exclude');
        }
    }
}
