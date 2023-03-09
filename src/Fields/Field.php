<?php

namespace Bredala\Validation\Fields;

class Field
{
    // -------------------------------------------------------------------------
    // Tools
    // -------------------------------------------------------------------------

    public static function trigger(string $error)
    {
        throw new FieldException($error);
    }

    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    public static function skip(mixed $value)
    {
        if ($value === null) {
            throw new SkipException('skip');
        }
    }

    public static function required(mixed $value)
    {
        if ($value === null) {
            self::trigger('required');
        }
    }

    public static function match(mixed $value, mixed $compare)
    {
        if ($value !== $compare) {
            self::trigger('match');
        }
    }

    public static function differ(mixed $value, mixed $compare)
    {
        if ($value === $compare) {
            self::trigger('differ');
        }
    }

    public static function include(mixed $value, array $items)
    {
        if ($value !== null && !in_array($value, $items)) {
            self::trigger('include');
        }
    }

    public static function exclude(mixed $value, array $items)
    {
        if ($value !== null && in_array($value, $items)) {
            self::trigger('exclude');
        }
    }

    // -------------------------------------------------------------------------
}
