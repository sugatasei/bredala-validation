<?php

namespace Bredala\Validation\Elements;

use Bredala\Validation\Result;
use Bredala\Validation\Schema;
use Bredala\Validation\ValidationException;
use Stringable;

/**
 * One of several literal values or schemas, tried in order.
 */
class AnyOf extends Schema
{
    private array $variants;

    public function __construct(mixed ...$variants)
    {
        $this->variants = $variants;
    }

    // -------------------------------------------------------------------------
    // Processing
    // -------------------------------------------------------------------------

    protected function sanitize(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }

    protected function processValue(mixed $value): Result
    {
        $best = null;

        foreach ($this->variants as $variant) {
            if ($variant instanceof Schema) {
                $result = $variant->validate($value);
                if ($result->isValid()) {
                    return $this->accept($result->values(), $result->data());
                }
                if ($best === null || self::count($result->error()) < self::count($best->error())) {
                    $best = $result;
                }
            } elseif (self::matches($value, $variant)) {
                return $this->accept($variant, $variant);
            }
        }

        return $best ?? new Result(is_scalar($value) ? $value : null, 'anyOf');
    }

    private function accept(mixed $value, mixed $data): Result
    {
        try {
            $this->runAsserts($value);
        } catch (ValidationException $ex) {
            return new Result($value, $ex->getMessage());
        }

        return $this->complete($value, $data);
    }

    private static function count(string|array|null $error): int
    {
        if ($error === null) {
            return 0;
        }

        return is_string($error) ? 1 : array_sum(array_map([self::class, 'count'], $error));
    }

    /**
     * A form sends strings: '2' matches the literal 2, ' paid ' matches 'paid'.
     */
    private static function matches(mixed $value, mixed $literal): bool
    {
        if ($value === $literal) {
            return true;
        }

        $scalar = fn($v) => is_scalar($v) || $v instanceof Stringable;

        return $scalar($value) && $scalar($literal) && !is_bool($literal)
            && trim((string) $value) === (string) $literal;
    }
}
