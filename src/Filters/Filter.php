<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class Filter
{
    public static function required(mixed $value): mixed
    {
        if ($value === null || $value = '' || $value === []) {
            throw new ValidationException('required');
        }

        return $value;
    }

    public static function include(mixed $value, array $items): mixed
    {
        if ($value !== null && !in_array($value, $items)) {
            throw new ValidationException('include');
        }

        return $value;
    }

    public static function exclude(mixed $value, array $items): mixed
    {
        if ($value !== null && in_array($value, $items)) {
            throw new ValidationException('exclude');
        }

        return $value;
    }
}
