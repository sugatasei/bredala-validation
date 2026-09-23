<?php

namespace Bredala\Validation\Elements;

use Bredala\Validation\Rules\StringRule;

/**
 * A string, sanitized by a function of Bredala\Validation\Filters\StringFilter.
 */
class StringType extends Type
{
    /**
     * Minimum length, in characters.
     */
    public function min(int $min): static
    {
        return $this->rule('min', fn(string $v) => StringRule::minLength($v, $min));
    }

    /**
     * Maximum length, in characters.
     */
    public function max(int $max): static
    {
        return $this->rule('max', fn(string $v) => StringRule::maxLength($v, $max));
    }

    /**
     * Exact length, in characters.
     */
    public function length(int $length): static
    {
        return $this->rule('length', fn(string $v) => StringRule::length($v, $length));
    }

    /**
     * Letters only, accented included.
     */
    public function alpha(): static
    {
        return $this->rule('alpha', StringRule::isAlpha(...));
    }

    /**
     * Letters and digits only.
     */
    public function alnum(): static
    {
        return $this->rule('alnum', StringRule::isAlnum(...));
    }

    /**
     * ASCII digits only, leading zeros kept: a zip code, a phone number...
     */
    public function digits(): static
    {
        return $this->rule('digits', StringRule::isDigits(...));
    }

    /**
     * A regular expression the whole string must match, without delimiters.
     */
    public function pattern(string $pattern): static
    {
        return $this->rule('pattern', fn(string $v) => StringRule::matches($v, $pattern));
    }
}
