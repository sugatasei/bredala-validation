<?php

namespace Bredala\Validation\Rules;

use DateTimeImmutable;

class StringRule
{
    /**
     * Length in characters (mb_strlen), not bytes.
     */
    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen($value) >= $min;
    }

    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen($value) <= $max;
    }

    public static function length(string $value, int $length): bool
    {
        return mb_strlen($value) === $length;
    }

    /**
     * Letters only, accented included.
     */
    public static function isAlpha(string $value): bool
    {
        return preg_match('/^\p{L}+$/Du', $value) === 1;
    }

    /**
     * Letters and digits only, accented letters included.
     */
    public static function isAlnum(string $value): bool
    {
        return preg_match('/^[\p{L}\p{Nd}]+$/Du', $value) === 1;
    }

    /**
     * ASCII digits only: '0123' passes, '-1' and '1.5' do not.
     */
    public static function isDigits(string $value): bool
    {
        return preg_match('/^[0-9]+$/D', $value) === 1;
    }

    /**
     * The whole string must match the regular expression, given without
     * delimiters. Unicode is on.
     */
    public static function matches(string $value, string $pattern): bool
    {
        return preg_match('~^(?:' . str_replace('~', '\~', $pattern) . ')$~Du', $value) === 1;
    }

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

    /**
     * A UUID in its canonical form (8-4-4-4-12 hex digits), any version.
     */
    public static function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/Di', $value) === 1;
    }

    /**
     * An IP address; $version restricts it to 4 or 6.
     */
    public static function isIp(string $value, ?int $version = null): bool
    {
        $flags = match ($version) {
            4 => FILTER_FLAG_IPV4,
            6 => FILTER_FLAG_IPV6,
            default => 0,
        };

        return filter_var($value, FILTER_VALIDATE_IP, $flags) !== false;
    }

    public static function isJson(string $value): bool
    {
        return json_validate($value);
    }

    public static function isEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * An http or https URL.
     */
    public static function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
