<?php

namespace Bredala\Validation;

use Bredala\Validation\Fields\ArrayField;
use Bredala\Validation\Fields\BooleanField;
use Bredala\Validation\Fields\DecimalField;
use Bredala\Validation\Fields\FieldException;
use Bredala\Validation\Fields\IntegerField;
use Bredala\Validation\Fields\SkipException;
use Bredala\Validation\Fields\StringField;

class Form
{
    private bool $isValid = false;
    private array $filters = [];
    private array $rules = [];
    private array $defaults = [];
    private array $values = [];
    private array $errors = [];

    // -------------------------------------------------------------------------
    // Field configuration
    // -------------------------------------------------------------------------

    public function field(string $name, callable $filter, callable $rule, mixed $default = null): static
    {
        $this->filters[$name] = $filter;
        $this->rules[$name] = $rule;
        $this->defaults[$name] = $default;
        $this->values[$name] = $default;
        return $this;
    }

    public function boolean(string $name, callable $rule, mixed $default = null): static
    {
        return $this->field($name, [BooleanField::class, 'sanitize'], $rule, $default);
    }

    public function integer(string $name, callable $rule, mixed $default = null): static
    {
        return $this->field($name, [IntegerField::class, 'sanitize'], $rule, $default);
    }

    public function decimal(string $name, callable $rule, mixed $default = null): static
    {
        return $this->field($name, [DecimalField::class, 'sanitize'], $rule, $default);
    }

    public function input(string $name, callable $rule, mixed $default = null): static
    {
        return $this->field($name, [StringField::class, 'input'], $rule, $default);
    }

    public function text(string $name, callable $rule, mixed $default = null): static
    {
        return $this->field($name, [StringField::class, 'text'], $rule, $default);
    }

    public function mapBoolean(string $name, callable $rule): static
    {
        return $this->field($name, [ArrayField::class, 'mapBoolean'], $rule);
    }

    public function mapInteger(string $name, callable $rule): static
    {
        return $this->field($name, [ArrayField::class, 'mapInteger'], $rule);
    }

    public function mapDecimal(string $name, callable $rule): static
    {
        return $this->field($name, [ArrayField::class, 'mapDecimal'], $rule);
    }

    public function mapInput(string $name, callable $rule): static
    {
        return $this->field($name, [ArrayField::class, 'mapInput'], $rule);
    }

    public function mapText(string $name, callable $rule): static
    {
        return $this->field($name, [ArrayField::class, 'mapText'], $rule);
    }

    public function mapArray(string $name, callable $rule): static
    {
        return $this->field($name, [ArrayField::class, 'mapArray'], $rule);
    }

    // -------------------------------------------------------------------------
    // Setter
    // -------------------------------------------------------------------------

    public function validate(array $data): bool
    {
        $this->isValid = true;
        $this->errors = [];
        $this->values = [];

        foreach ($this->filters as $name => $filter) {
            try {
                $this->values[$name] = $filter($data[$name] ?? $this->defaults[$name]);
            } catch (FieldException $ex) {
                $this->setError($name, $ex->getMessage());
                $this->values[$name] = $this->defaults[$name];
            }
        }

        foreach ($this->rules as $name => $rule) {

            if ($this->error($name)) {
                continue;
            }

            try {
                $rule($this->values[$name]);
            } catch (FieldException $ex) {
                $this->setError($name, $ex->getMessage());
            } catch (SkipException $ex) {
            }
        }

        return $this->isValid();
    }

    public function setValue(string $name, mixed $value): static
    {
        $this->values[$name] = $value;
        return $this;
    }

    public function setError(string $name, string $error): static
    {
        $this->isValid = false;
        $this->errors[$name] = $error;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Results
    // -------------------------------------------------------------------------

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function error(string $name): ?string
    {
        return $this->errors[$name] ?? null;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function value(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function values(): array
    {
        return $this->values;
    }

    // -------------------------------------------------------------------------
}
