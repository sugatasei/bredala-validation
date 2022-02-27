<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\Helper;
use Bredala\Validation\ValidationException;

class StringFilter extends Filter
{
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

    public static function removeLines(?string $value): ?string
    {
        if ($value) {
            $value = str_replace("\n", ' ', $value);
        }

        return $value;
    }

    public static function sanitizeUrl(mixed $value): ?string
    {
        if (!$value || !is_string($value)) {
            return null;
        }

        return filter_var($value, FILTER_SANITIZE_URL) ?: null;
    }

    /**
     * @param string $value
     * @param integer $min
     */
    public static function min(?string $value, int $min): ?string
    {
        if ($value === null) {
            return $value;
        }

        $count = mb_strlen($value);

        if ($count < $min) {
            throw new ValidationException('min');
        }

        return $value;
    }

    /**
     * @param string $value
     * @param integer $max
     */
    public static function max(?string $value, int $max): ?string
    {
        if ($value === null) {
            return $value;
        }

        $count = mb_strlen($value);

        if ($count > $max) {
            throw new ValidationException('max');
        }

        return $value;
    }

    /**
     * @param string $value
     * @param integer $min
     * @param integer $max
     */
    public static function range(?string $value, int $min, int $max): ?string
    {
        if ($value === null) {
            return $value;
        }

        $count = mb_strlen($value);

        if ($count < $min) {
            throw new ValidationException('range');
        }
        if ($count > $max) {
            throw new ValidationException('range');
        }

        return $value;
    }

    /**
     * @param string $value
     * @param string $num
     */
    public static function equal(?string $value, string $text): ?string
    {
        if ($value !== null && $value !== $text) {
            throw new ValidationException('equal');
        }

        return $value;
    }

    /**
     * @param string $value
     * @param string $text
     */
    public static function differ(?string $value, string $text): ?string
    {
        if ($value !== null && $value === $text) {
            throw new ValidationException('equal');
        }

        return $value;
    }

    /**
     * @param string $value
     * @param string $match
     */
    public static function match(?string $value, string $pattern): ?string
    {
        if ($value !== null && preg_match($pattern, $value) !== 1) {
            throw new ValidationException('match');
        }

        return $value;
    }

    /**
     * @param string $value
     */
    public static function email(?string $value): ?string
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('email');
        }

        return $value;
    }

    /**
     * @param string $value
     */
    public static function ip(?string $value): ?string
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_IP)) {
            throw new ValidationException('ip');
        }

        return $value;
    }

    /**
     * @param string $value
     */
    public static function ipv4(?string $value): ?string
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new ValidationException('ip');
        }

        return $value;
    }

    /**
     * @param string $value
     */
    public static function ipv6(?string $value): ?string
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new ValidationException('ip');
        }

        return $value;
    }

    public static function url(?string $value): ?string
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new ValidationException('url');
        }

        return $value;
    }
}
