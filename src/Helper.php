<?php

namespace Bredala\Validation;

class Helper
{
    /**
     * Auto-sanitize a value
     *
     * @param mixed $value
     * @return mixed
     */
    public static function sanitizeValue($value)
    {
        if ($value) {
            if (is_string($value)) {
                $value = self::sanitizeString($value);
            } elseif (is_array($value)) {
                $values = [];
                foreach ($value as $k => $v) {
                    $values[$k] = static::sanitizeValue($v);
                }
                return $values;
            }
        }

        return $value;
    }

    /**
     * Sanitize a string
     *
     * @param string $value
     * @return string
     */
    public static function sanitizeString(string $value)
    {
        $value = static::decode($value);
        $value = static::removeInvisible($value);
        $value = static::normalize($value);
        $value = static::cleanUrl($value);
        $value = static::stripTags($value);
        return trim($value);
    }

    /**
     * Html decode
     *
     * @param string $input
     * @return string
     */
    public static function decode(string $input): string
    {
        return html_entity_decode($input, ENT_QUOTES | ENT_HTML401);
    }

    /**
     * Remove invisible characters
     *
     * @param string $input
     * @return string
     */
    public static function removeInvisible(string $input): string
    {
        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        $invisibles = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x80-\x9F]+/Su';

        $count = 1;
        while ($count) {
            $input = preg_replace($invisibles, '', $input, -1, $count);
        }

        return $input;
    }

    /**
     * Normalize new lines and spaces
     *
     * @param string $input
     * @return string
     */
    public static function normalize(string $input): string
    {
        $search = [];
        $search[] = '/(?:\r\n|[\r\n])/'; // new lines
        $search[] = '/[\xA0\xAD\x{2000}-\x{200F}\x{2028}-\x{202F}\x{205F}-\x{206F}]/u'; // spaces

        $replace = [PHP_EOL, ' '];

        return preg_replace($search, $replace, $input);
    }

    /**
     * Remove invisible charaters from urls
     *
     * @param string $input
     * @return string
     */
    public static function cleanUrl(string $input): string
    {
        return preg_replace_callback("#://([^\s]+)#", [static::class, 'matchUrl'], $input);
    }

    /**
     * Preg replace callback for cleanUrl
     *
     * @param array $matches
     * @return string
     */
    private static function matchUrl(array $matches): string
    {
        $invisible = [];
        $invisible[] = '/%0[0-8bcef]/i'; // url encoded 00-08, 11, 12, 14, 15
        $invisible[] = '/%1[0-9a-f]/i'; // url encoded 16-31
        $invisible[] = '/%7f/i'; // url encoded 127
        $invisible[] = '/%8[def]/i'; // url encoded 128-143
        $invisible[] = '/%9[0-9a-f]/i'; // url encoded 144-159

        $input = $matches[1] ?? '';
        $count = 1;
        while ($count) {
            $input = preg_replace($invisible, '', $input, -1, $count);
        }

        return $input;
    }

    /**
     * Strip tags from an Html string
     *
     * @param string $input
     * @return string
     */
    public static function stripTags(string $input): string
    {
        return strip_tags($input);
    }
}
