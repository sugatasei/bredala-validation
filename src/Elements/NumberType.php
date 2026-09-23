<?php

namespace Bredala\Validation\Elements;

use Bredala\Validation\Rules\NumberRule;

/**
 * An int or a float, sanitized by IntegerFilter or DecimalFilter.
 */
class NumberType extends Type
{
    public function min(int|float $min): static
    {
        return $this->rule('min', fn(int|float $v) => NumberRule::min($v, $min));
    }

    public function max(int|float $max): static
    {
        return $this->rule('max', fn(int|float $v) => NumberRule::max($v, $max));
    }

    /**
     * Like the step attribute of <input type="number">, counted from 0.
     */
    public function multipleOf(int|float $step): static
    {
        return $this->rule('multipleOf', fn(int|float $v) => NumberRule::isMultipleOf($v, $step));
    }
}
