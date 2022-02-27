<?php

namespace Bredala\Validation;

class Rule
{
    private $callback;
    private array $params = [];

    public function __construct(callable $callback, array $params = [])
    {
        $this->callback = $callback;
        $this->params = $params;
    }

    public function run(mixed $value)
    {
        $params = array_merge([$value], $this->params);
        return call_user_func_array($this->callback, $params);
    }
}
