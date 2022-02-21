<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\Helper;
use Bredala\Validation\ValidationException;

class StringFilter extends Filter
{
    /**
     * Text validation
     *
     * @param mixed $value
     * @return string|null
     */
    public static function sanitize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return Helper::sanitizeString($value) ?: null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        throw new ValidationException('type');
    }

    /**
     * Varchar validation
     *
     * @param mixed $value
     */
    public static function noSpace($value): void
    {
        if (preg_match("/\s/", $value)) {
            throw new ValidationException('nospace');
        }
    }

    /**
     * @param string $value
     * @param integer $min
     */
    public static function min(string $value, int $min): void
    {
        if (mb_strlen($value) < $min) {
            throw new ValidationException('min');
        }
    }

    /**
     * @param string $value
     * @param integer $max
     */
    public static function max(string $value, int $max): void
    {
        if (mb_strlen($value) > $max) {
            throw new ValidationException('max');
        }
    }

    /**
     * @param string $value
     * @param integer $min
     * @param integer $max
     */
    public static function range(string $value, int $min, int $max): void
    {
        if (mb_strlen($value) < $min) {
            throw new ValidationException('range');
        }
        if (mb_strlen($value) > $max) {
            throw new ValidationException('range');
        }
    }

    /**
     * @param string $value
     * @param string $num
     */
    public static function equal(string $value, string $text): void
    {
        if ($value !== $text) {
            throw new ValidationException('equal');
        }
    }

    /**
     * @param string $value
     * @param string $text
     */
    public static function differ(string $value, string $text): void
    {
        if ($value === $text) {
            throw new ValidationException('equal');
        }
    }

    /**
     * @param string $value
     * @param string $match
     */
    public static function match(string $value, string $pattern): void
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new ValidationException('match');
        }
    }

    /**
     * @param string $value
     */
    public static function email(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('email');
        }
    }

    /**
     * @param string $value
     */
    public static function ip(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            throw new ValidationException('ip');
        }
    }

    /**
     * @param string $value
     */
    public static function ipv4(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new ValidationException('ip');
        }
    }

    /**
     * @param string $value
     */
    public static function ipv6(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new ValidationException('ip');
        }
    }

    public static function url(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new ValidationException('url');
        }
    }
}
