<?php

namespace Bredala\Validation\Filters;

use Bredala\Validation\Schema;
use Stringable;

class StringFilter
{
    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    public static function input(mixed $value): ?string
    {
        return self::sanitize($value, false);
    }

    public static function text(mixed $value): ?string
    {
        return self::sanitize($value, true);
    }

    /**
     * Converts to a string without cleaning it: only '' becomes null.
     */
    public static function raw(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        Schema::fail('type');
    }

    public static function sanitize(mixed $value, bool $multiline = false): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (!is_string($value)) {
            Schema::fail('type');
        }

        // convert into valid utf-8 string
        $value = htmlentities($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8", false);
        // decode entities
        $value = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8");
        // strip html tags
        $value = self::stripTags($value);
        // remove not printable characters (including)
        $value = preg_replace('/[\x{FFFD}\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x80-\x9F]/u', "", $value);
        // replace special spaces
        $value = preg_replace('/[\xA0\xAD\x{2000}-\x{200F}\x{2028}-\x{202F}\x{205F}-\x{206F}]/u', " ", $value);

        if ($multiline) {
            // convert all whitespace except new lines into space
            $value = preg_replace('/[^\S\n]+/', " ", $value);
            // trim each lines
            $value = preg_replace('/ *\n */', "\n", $value);
            // two sets of consecutive lines at maximum
            $value = preg_replace('/\n{3,}/', "\n\n", $value);
        } else {
            // replace all whitespace into space
            $value = preg_replace('/\s/', " ", $value);
        }

        // remove consecutive spaces
        $value = preg_replace('/ +/', " ", $value);

        // trim all
        $value = trim($value);

        return $value === "" ? null : $value;
    }

    public static function stripTags(string $string): string
    {
        $tag = "~" . uniqid() . "~";
        $string = preg_replace("/(<)(\d)/", "$1 {$tag}$2", $string);
        $string = strip_tags($string);
        $string = preg_replace("/(<) {$tag}(\d)/", "$1$2", $string);
        return $string;
    }

    public static function sanitizeUrl(mixed $value): ?string
    {
        return self::sanitizeType($value, FILTER_SANITIZE_URL);
    }

    public static function sanitizeEmail(mixed $value): ?string
    {
        return self::sanitizeType($value, FILTER_SANITIZE_EMAIL);
    }

    public static function sanitizeType(mixed $value, int $type): ?string
    {
        if (!$value || !is_string($value)) {
            return null;
        }

        return filter_var($value, $type) ?: null;
    }

    // -------------------------------------------------------------------------
}
