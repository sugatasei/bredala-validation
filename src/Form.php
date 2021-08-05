<?php

namespace Bredala\Validation;

class Form
{
    /**
     * @var Field[]
     */
    private array $fields = [];
    private array $values = [];
    private array $errors = [];

    // -------------------------------------------------------------------------

    /**
     * @param string $field
     * @return Field
     */
    public function field(string $field): Field
    {
        if (!isset($this->fields[$field])) {
            $this->fields[$field] = new Field();
        }

        return $this->fields[$field];
    }

    // -------------------------------------------------------------------------

    /**
     * @param string $field
     * @param mixed $value
     */
    public function setValue(string $field, $value)
    {
        $this->values[$field] = $value;
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
        return $this->errors[$field] ?? '';
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
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
            try {
                $value = $data[$name] ?? null;
                foreach ($field->getRules() as $rule) {
                    $params = array_merge([$value], $rule['params']);
                    $value = call_user_func_array($rule['callback'], $params);
                    $this->setValue($name, $value);
                }
            } catch (ValidationException $ex) {
                $this->setError($name, $ex->getMessage());
            }
        }

        return empty($this->errors);
    }

    // -------------------------------------------------------------------------
}
