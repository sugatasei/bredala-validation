<?php

namespace Bredala\Validation;

use Bredala\Validation\Filters\ArrayFilter;
use Bredala\Validation\Filters\BoolFilter;
use Bredala\Validation\Filters\IntFilter;
use Bredala\Validation\Filters\NumberFilter;
use Bredala\Validation\Filters\StringFilter;

class Form
{
    private array $fields = [];
    private array $values = [];
    private array $errors = [];
    private array $messages = [];

    /**
     * Add field
     *
     * @param string $name
     * @param callable|null $filter
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function field(string $name, ?callable $filter = null, ?callable $rule = null, mixed $default = null): static
    {
        $this->fields[$name] = [
            "filter" => $filter,
            "rule" => $rule,
            "form" => null,
            "default" => $default,
        ];

        return $this;
    }

    /**
     * @param string $name
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function boolean(string $name, ?callable $rule = null, mixed $default = null): static
    {
        return $this->field($name, [BoolFilter::class, "sanitize"], $rule, $default);
    }

    /**
     * @param string $name
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function integer(string $name, ?callable $rule = null, mixed $default = null): static
    {
        return $this->field($name, [IntFilter::class, "sanitize"], $rule, $default);
    }

    /**
     * @param string $name
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function number(string $name, ?callable $rule = null, mixed $default = null): static
    {
        return $this->field($name, [NumberFilter::class, "sanitize"], $rule, $default);
    }

    /**
     * @param string $name
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function string(string $name, ?callable $rule = null, mixed $default = null): static
    {
        return $this->field($name, [StringFilter::class, "sanitize"], $rule, $default);
    }

    /**
     * @param string $name
     * @param callable|null $rule
     * @return static
     */
    public function arrayOfInteger(string $name, ?callable $rule = null): static
    {
        return $this->field($name, [ArrayFilter::class, "sanitizeInt"], $rule, []);
    }
    /**
     * @param string $name
     * @param callable|null $rule
     * @return static
     */
    public function arrayOfNumber(string $name, ?callable $rule = null): static
    {
        return $this->field($name, [ArrayFilter::class, "sanitizeNumber"], $rule, []);
    }

    /**
     * @param string $name
     * @param callable|null $rule
     * @param mixed $default
     * @return static
     */
    public function arrayOfString(string $name, ?callable $rule = null): static
    {
        return $this->field($name, [ArrayFilter::class, "sanitizeText"], $rule, []);
    }

    /**
     * @param string $name
     * @param Form $form
     * @param callable|null $rule
     * @return static
     */
    public function arrayOfForm(string $name, Form $form, ?callable $rule = null): static
    {
        $this->field($name, [ArrayFilter::class, "sanitizeObject"], $rule, []);
        $this->fields[$name]['form'] = $form;
        return $this;
    }

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


        foreach ($this->fields as $name => $field) {
            $filter = $field["filter"];
            $rule = $field["rule"];
            $form = $field["form"];
            $default = $field["default"];
            $value = $data[$name] ?? $default;

            // Filter
            try {
                if ($filter && $value !== $default) {
                    $value = call_user_func($filter, $value) ?? $default;
                }
            } catch (ValidationException $ex) {
                $this->setError($name, $ex->getMessage());
                $value = $default;
            }

            /**
             * Array of Form
             *
             * @var Form $form
             */
            if ($form && is_array($value) && $value) {
                foreach ($value as $k => $v) {
                    if (!$form->validate($v)) {
                        $this->setError($name, 'type');
                        foreach ($form->getErrors() as $f => $e) {
                            $this->setError("{$name}.{$k}.{$f}", $e);
                            $this->setDefaultMessage("{$name}.{$k}.{$f}", $form->getMessage($f));
                        }
                    }
                    $value[$k] = $form->getValues();
                }
            }

            // Set value
            $this->setValue($name, $value);

            // Rule
            if ($rule && !$this->getError($name)) {
                try {
                    call_user_func($rule, $value);
                } catch (ValidationException $ex) {
                    $this->setError($name, $ex->getMessage());
                }
            }
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
