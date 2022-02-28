<?php

namespace Bredala\Validation;

use Bredala\Validation\Filters\ArrayFilter;
use Bredala\Validation\Filters\BoolFilter;
use Bredala\Validation\Filters\Filter;
use Bredala\Validation\Filters\IntFilter;
use Bredala\Validation\Filters\NumberFilter;
use Bredala\Validation\Filters\StringFilter;
use JsonSerializable;

class Field implements JsonSerializable
{
    private string $name;

    /**
     * @var Rule[]
     */
    private array $rules = [];
    private ?Form $form = null;
    private array $messages = [];
    private mixed $value = null;
    private string $error = '';
    private mixed $default = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    // -------------------------------------------------------------------------
    // Name
    // -------------------------------------------------------------------------

    public function name(): string
    {
        return $this->name;
    }

    // -------------------------------------------------------------------------
    // Rules
    // -------------------------------------------------------------------------

    public function addRule(callable $rule, array $params = []): static
    {
        $this->rules[] = new Rule($rule, $params);
        return $this;
    }

    /**
     * @return Rule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    // -------------------------------------------------------------------------

    public function required(): static
    {
        return $this->addRule([Filter::class, 'required']);
    }

    public function include(array $items): static
    {
        return $this->addRule([Filter::class, 'include'], [$items]);
    }

    public function exclude(array $items): static
    {
        return $this->addRule([Filter::class, 'exclude'], [$items]);
    }

    // -------------------------------------------------------------------------

    public function fake(): static
    {
        return $this->addRule([Filter::class, 'fake']);
    }

    // -------------------------------------------------------------------------

    public function boolean(): static
    {
        return $this->addRule([BoolFilter::class, "sanitize"]);
    }

    // -------------------------------------------------------------------------

    public function integer(): static
    {
        return $this->addRule([IntFilter::class, "sanitize"]);
    }

    // -------------------------------------------------------------------------

    public function number(): static
    {
        return $this->addRule([NumberFilter::class, "sanitize"]);
    }

    // -------------------------------------------------------------------------

    public function string(bool $multiline = false): static
    {
        return $this->addRule([StringFilter::class, "sanitize"], [$multiline]);
    }

    public function url(): static
    {
        return $this
            ->addRule([StringFilter::class, "sanitizeUrl"])
            ->addRule([StringFilter::class, "url"]);
    }

    public function minLen(int $min)
    {
        return $this->addRule([StringFilter::class, "min"], [
            "min" => $min
        ]);
    }

    public function maxLen(int $max)
    {
        return $this->addRule([StringFilter::class, "max"], [
            "max" => $max
        ]);
    }

    public function rangeLen(int $min, int $max)
    {
        return $this->addRule([StringFilter::class, "range"], [
            "min" => $min,
            "max" => $max
        ]);
    }

    public function match(string $pattern)
    {
        return $this->addRule([StringFilter::class, "match"], [$pattern]);
    }

    // -------------------------------------------------------------------------

    public function array(): static
    {
        $this->setDefault([]);
        $this->addRule([ArrayFilter::class, "sanitize"]);
        return $this;
    }

    public function arrayMap(callable $callback): static
    {
        return $this->addRule([ArrayFilter::class, 'map'], [$callback]);
    }

    public function arrayInclude(array $items): static
    {
        return $this->addRule([ArrayFilter::class, 'include'], [$items]);
    }

    public function arrayExclude(array $items): static
    {
        return $this->addRule([ArrayFilter::class, 'exclude'], [$items]);
    }

    public function arrayForm(Form $form): static
    {
        $this->array();
        $this->arrayMap([ArrayFilter::class, "sanitizeObject"]);
        $this->form = $form;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Default values
    // -------------------------------------------------------------------------

    public function setDefault(mixed $value): static
    {
        $this->default = $value;
        return $this;
    }

    public function getDefault(): mixed
    {
        return $this->defaults;
    }

    // -------------------------------------------------------------------------
    // Values
    // -------------------------------------------------------------------------

    public function setValue(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getValue(): mixed
    {
        return $this->value ?? $this->default;
    }

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------

    public function setError(string $error)
    {
        $this->error = $error;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function isValid(): bool
    {
        return $this->error === '';
    }

    // -------------------------------------------------------------------------
    // Error mesages
    // -------------------------------------------------------------------------

    public function setMessage(string $error, string $message): static
    {
        $this->messages[$error] = $message;

        return $this;
    }

    public function setDefaultMessage(string $message): static
    {
        return $this->setMessage("default", $message);
    }

    /**
     * @param string $field
     * @return string
     */
    public function getMessage(): string
    {
        if (!($error = $this->getError())) {
            return "";
        }

        return $this->messages[$error] ?? $this->messages["default"] ?? $error;
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function validate(mixed $value): bool
    {
        $this->value = $this->default;
        $this->error = '';

        // Run each rules
        $nbValid = 0;
        try {
            foreach ($this->rules as $rule) {
                $value = $rule->run($value);
                $nbValid++;
            }
        } catch (ValidationException $ex) {
            $this->error = $ex->getMessage();
        }

        // No rules are valid
        if ($nbValid === 0) {
            $value = $this->default;
        }

        // Nested forms
        if ($this->form && is_array($value) && $value) {
            foreach ($value as $k => $v) {
                $form = clone $this->form;
                $isValid = $form->validate($v);
                if (!$isValid && !$this->error) {
                    $this->error = 'form';
                }
                $value[$k] = $form;
            }
        }

        $this->value = $value;

        return $this->isValid();
    }

    // -------------------------------------------------------------------------

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    // -------------------------------------------------------------------------
}
