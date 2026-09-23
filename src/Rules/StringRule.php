<?php

namespace Bredala\Validation\Rules;

use DateTimeImmutable;

class StringRule
{
    /**
     * Strict parsing: the parsed date must format back to the exact input,
     * which rejects overflows (2026-02-30, 25:00) and missing leading zeros.
     */
    public static function isDate(string $value, string ...$formats): bool
    {
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date && $date->format(strtr($format, ['!' => '', '|' => ''])) === $value) {
                return true;
            }
        }

        return false;
    }
}
