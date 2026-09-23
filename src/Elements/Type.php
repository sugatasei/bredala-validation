<?php

namespace Bredala\Validation\Elements;

use Bredala\Validation\Schema;

/**
 * A scalar value, sanitized by a function of Bredala\Validation\Filters.
 */
class Type extends Schema
{
    /** @var callable|null */
    private mixed $sanitizer;
    private int|float|null $min = null;
    private int|float|null $max = null;
    private ?string $pattern = null;
    private array $formats = [];

    public function __construct(?callable $sanitizer = null)
    {
        $this->sanitizer = $sanitizer;
    }

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /**
     * Minimum length of a string (in characters) or minimum value of a number.
     */
    public function min(int|float $min): static
    {
        $this->min = $min;
        return $this;
    }

    /**
     * Maximum length of a string (in characters) or maximum value of a number.
     */
    public function max(int|float $max): static
    {
        $this->max = $max;
        return $this;
    }

    /**
     * A regular expression the whole string must match, without delimiters.
     */
    public function pattern(string $pattern): static
    {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * @internal A check of the value's format, used by Schema::date(), email()...
     */
    public function format(callable $check, string $code): static
    {
        $this->formats[] = [$check, $code];
        return $this;
    }

    // -------------------------------------------------------------------------
    // Processing
    // -------------------------------------------------------------------------

    protected function sanitize(mixed $value): mixed
    {
        return $this->sanitizer ? ($this->sanitizer)($value) : $value;
    }

    protected function check(mixed $value): void
    {
        $measure = match (true) {
            is_string($value) => mb_strlen($value),
            is_int($value), is_float($value) => $value,
            is_array($value) => count($value),
            default => null,
        };

        if ($measure !== null && $this->min !== null && $measure < $this->min) {
            Schema::fail('min');
        }

        if ($measure !== null && $this->max !== null && $measure > $this->max) {
            Schema::fail('max');
        }

        if ($this->pattern !== null && is_string($value)
            && !preg_match('~^(?:' . str_replace('~', '\~', $this->pattern) . ')$~Du', $value)) {
            Schema::fail('pattern');
        }

        foreach ($this->formats as [$check, $code]) {
            if (!$check($value)) {
                Schema::fail($code);
            }
        }
    }
}
