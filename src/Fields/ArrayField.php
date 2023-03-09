<?php

namespace Bredala\Validation\Fields;

use Bredala\Validation\Form;

class ArrayField extends Field
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

        self::trigger('type');
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
        return self::map($value, [ArrayField::class, 'sanitize']);
    }

    public static function mapBoolean(mixed $value): ?array
    {
        return self::map($value, [BooleanField::class, 'sanitize']);
    }

    public static function mapInteger(mixed $value): ?array
    {
        return self::map($value, [IntegerField::class, 'sanitize']);
    }

    public static function mapDecimal(mixed $value): ?array
    {
        return self::map($value, [DecimalField::class, 'sanitize']);
    }

    public static function mapInput(mixed $value): ?array
    {
        return self::map($value, [StringField::class, 'input']);
    }

    public static function mapText(mixed $value): ?array
    {
        return self::map($value, [StringField::class, 'text']);
    }

    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    public static function nested(array $values, Form $parent, Form $child, array &$children = [])
    {
        $hasError = false;
        $output = [];
        $children = [];

        foreach ($values as $k => $v) {
            $children[$k] = clone $child;
            if (!$children[$k]->validate($v)) {
                $hasError = true;
            }
            $output[$k] = $children[$k]->values();
        }

        $parent->setValue('users', $output);

        if ($hasError) {
            self::trigger('child');
        }
    }

    public static function min(array $value, int $min)
    {
        $count = count($value);

        if ($count < $min) {
            self::trigger('min');
        }
    }

    /**
     * @param array $value
     * @param integer $max
     */
    public static function max(array $value, int $max)
    {
        $count = count($value);

        if ($count > $max) {
            self::trigger('max');
        }
    }

    /**
     * @param array $value
     * @param integer $min
     * @param integer $max
     */
    public static function range(array $value, int $min, int $max)
    {
        $count = count($value);

        if ($count < $min) {
            self::trigger('min');
        }

        if ($count > $max) {
            self::trigger('max');
        }
    }

    public static function include(mixed $value, array $items)
    {
        if (!$value || !$items || !is_array($value)) {
            return;
        }

        foreach ($value as $v) {
            parent::include($v, $items);
        }
    }

    public static function exclude(mixed $value, array $items)
    {
        if (!$value || !$items || !is_array($value)) {
            return;
        }

        foreach ($value as $v) {
            parent::exclude($v, $items);
        }
    }

    // -------------------------------------------------------------------------
}
