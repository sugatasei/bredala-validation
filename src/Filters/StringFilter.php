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
     * @param string $message
     * @return string|null
     */
    public static function sanitize($value, string $message = 'type')
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return Helper::sanitizeString($value);
        }

        if (is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }

        throw new ValidationException($message);
    }

    /**
     * Varchar validation
     *
     * @param mixed $value
     * @param string $message
     * @return string
     */
    public static function noSpace($value, string $message = 'type')
    {
        if (preg_match("/\s/", $value)) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param string $value
     * @param integer $min
     * @param string $message
     * @return string
     */
    public static function min(string $value, int $min, string $message = 'min'): string
    {
        if (mb_strlen($value) < $min) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param string $value
     * @param integer $max
     * @param string $message
     * @return string
     */
    public static function max(string $value, int $min, string $message = 'max'): string
    {
        if (mb_strlen($value) > $min) {
            throw new ValidationException($message);
        }

        return $value;
    }

    /**
     * @param string $value
     * @param integer $min
     * @param integer $max
     * @param string $message
     * @return string
     */
    public static function range(string $value, int $min, int $max, string $message = 'range'): string
    {
        self::min($value, $min, $message);
        self::max($value, $max, $message);

        return $value;
    }

    /**
     * @param string $value
     * @param string $match
     * @param string $message
     */
    public static function match(string $value, string $pattern, string $message = 'match'): string
    {
        if (preg_match($pattern, $value) === 1) {
            return $value;
        }

        throw new ValidationException($message);
    }

    /**
     * @param string $value
     * @param string $message
     * @return string
     */
    public static function email(string $value, string $message = 'email'): string
    {
        if (!!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }

        throw new ValidationException($message);
    }

    /**
     * @param string $value
     * @param string $message
     * @return string
     */
    public static function ip(string $value, string $message = 'ip'): string
    {
        if (!!filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }

        throw new ValidationException($message);
    }

    /**
     * @param string $value
     * @param string $message
     * @return string
     */
    public static function ipv4(string $value, string $message = 'ip'): string
    {
        if (!!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $value;
        }

        throw new ValidationException($message);
    }

    /**
     * @param string $value
     * @param string $message
     * @return string
     */
    public static function ipv6(string $value, string $message = 'ip'): string
    {
        if (!!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $value;
        }

        throw new ValidationException($message);
    }
}
