<?php

namespace Bredala\Validation;

final class Field
{
    private array $rules = [];

    public function addRule(callable $callback, ...$params): Field
    {
        $this->rules[] = [
            'callback' => $callback,
            'params' => $params
        ];

        return $this;
    }

    public function getRules(): array
    {
        return $this->rules;
    }
}
