<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\ValidationException;

class StringFilter extends Filter
{
    public static function sanitize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (!is_string($value)) {
            throw new ValidationException('type');
        }

        // convert into valid utf-8 string
        $value = htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8", false);
        // decode entities
        $value = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8");
        // strip html tags
        $value = strip_tags($value);
        // remove not printable characters (including)
        $value = preg_replace('/[\x{FFFD}\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x80-\x9F]/u', "", $value);
        // replace special spaces
        $value = preg_replace('/[\xA0\xAD\x{2000}-\x{200F}\x{2028}-\x{202F}\x{205F}-\x{206F}]/u', " ", $value);
        // convert all whitespace except new lines into space
        $value = preg_replace('/[^\S\n]+/', " ", $value);
        // trim each lines
        $value = preg_replace('/ *\n */', "\n", $value);
        // two sets of consecutive lines at maximum
        $value = preg_replace('/\n{3,}/', "\n\n", $value);
        // remove consecutive spaces
        $value = preg_replace('/ +/', " ", $value);
        // trim all
        $value = trim($value);

        return $value === "" ? null : $value;
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
