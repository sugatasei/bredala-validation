<?php

namespace Bredala\Validation;

use Bredala\Validation\Filters\ArrayFilter;
use Bredala\Validation\Filters\BoolFilter;
use Bredala\Validation\Filters\IntFilter;
use Bredala\Validation\Filters\NumberFilter;
use Bredala\Validation\Filters\StringFilter;

class Form
{
    /**
     * @var Form[]
     */
    private array $forms = [];
    private array $rules = [];
    private array $values = [];
    private array $defaults = [];
    private array $errors = [];
    private array $messages = [];

    /**
     * Add field
     *
     * @param string $name
     * @param callable $rule
     * @param mixed $default
     * @return static
     */
    public function addRule(string $field, callable $rule, array $params = []): static
    {
        $this->rules[$field][] = [$rule, $params];

        return $this;
    }

    /**
     * @param string $field
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function boolean(string $field): static
    {
        return $this->addRule($field, [BoolFilter::class, "sanitize"]);
    }

    /**
     * @param string $field
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function integer(string $field): static
    {
        return $this->addRule($field, [IntFilter::class, "sanitize"]);
    }

    /**
     * @param string $field
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function number(string $field): static
    {
        return $this->addRule($field, [NumberFilter::class, "sanitize"]);
    }

    /**
     * @param string $field
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function url(string $field): static
    {
        return $this->addRule($field, [StringFilter::class, "sanitizeUrl"]);
    }

    /**
     * @param string $field
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function string(string $field): static
    {
        return $this->addRule($field, [StringFilter::class, "sanitize"]);
    }

    /**
     * @param string $field
     * @param callable|null $rule
     * @return static
     */
    public function array(string $field): static
    {
        $this->setDefault($field, []);
        $this->addRule($field, [ArrayFilter::class, "sanitize"]);
        return $this;
    }

    public function map(string $field, callable $callback): static
    {
        return $this->addRule($field, [ArrayFilter::class, 'map'], [$callback]);
    }

    /**
     * @param string $field
     * @param Form $form
     * @param callable|null $rule
     * @return static
     */
    public function form(string $field, Form $form): static
    {
        $this->map($field, [ArrayFilter::class, "sanitizeObject"]);
        $this->forms[$field] = $form;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Values
    // -------------------------------------------------------------------------

    /**
     * @param string $field
     * @param mixed $value
     * @return static
     */
    public function setValue(string $field, $value): static
    {
        $this->values[$field] = $value;
        return $this;
    }

    /**
     * @param string $field
     * @return mixed
     */
    public function getValue(string $field)
    {
        return $this->values[$field] ?? null;
    }

    /**
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }

    // -------------------------------------------------------------------------
    // Default values
    // -------------------------------------------------------------------------

    public function setDefault(string $field, mixed $value): static
    {
        $this->defaults[$field] = $value;

        return $this;
    }

    public function getDefault(string $field): mixed
    {
        return $this->defaults[$field] ?? null;
    }

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------

    /**
     * @param string $field
     * @param string $error
     */
    public function setError(string $field, string $error)
    {
        $this->errors[$field] = $error;
    }

    /**
     * @param string $field
     * @return string
     */
    public function getError(string $field): string
    {
        return $this->errors[$field] ?? "";
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    // -------------------------------------------------------------------------
    // Error mesages
    // -------------------------------------------------------------------------

    public function setMessage(string $field, string $error, string $message): static
    {
        $this->messages[$field][$error] = $message;

        return $this;
    }

    public function setDefaultMessage(string $field, string $message): static
    {
        return $this->setMessage($field, "default", $message);
    }

    /**
     * @param string $field
     * @return string
     */
    public function getMessage(string $field): string
    {
        if (!($error = $this->getError($field))) {
            return "";
        }

        return $this->messages[$field][$error]
            ?? $this->messages[$field]["default"]
            ?? $error;
    }

    public function getMessages(): array
    {
        $messages = [];

        foreach ($this->errors as $field => $error) {
            $messages[$field] = $this->messages[$field][$error]
                ?? $this->messages[$field]["default"]
                ?? $error;
        }

        return $messages;
    }

    // -------------------------------------------------------------------------

    /**
     * @param array $data
     * @return boolean
     */
    public function validate(array $data): bool
    {
        $this->values = [];
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $default = $this->getDefault($field);
            $value = $data[$field] ?? $default;

            $isChecked = false;

            // Each rules
            try {
                foreach ($rules as $rule) {
                    if ($value === $default) {
                        break;
                    }
                    $value = call_user_func_array($rule[0], $value, ...$rule[1]) ?? $default;
                    $isChecked = true;
                }
            } catch (ValidationException $ex) {
                $this->setError($field, $ex->getMessage());
            }

            // First rule failed
            if (!$isChecked && $value !== $default) {
                $value = $default;
            }

            // Nested forms
            if (($form = $this->forms[$field] ?? null) && is_array($value) && $value) {
                foreach ($value as $k => $v) {
                    if (!$form->validate($v)) {
                        $this->setError($field, 'type');
                        foreach ($form->getErrors() as $f => $e) {
                            $this->setError("{$field}.{$k}.{$f}", $e);
                            $this->setDefaultMessage("{$field}.{$k}.{$f}", $form->getMessage($f));
                        }
                    }
                    $value[$k] = $form->getValues();
                }
            }

            // Set value
            $this->setValue($field, $value);
        }

        return $this->isValid();
    }

    /**
     * @return boolean
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    // -------------------------------------------------------------------------
}
