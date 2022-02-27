<?php

namespace Bredala\Validation;

use JsonSerializable;

class Form implements JsonSerializable
{
    /**
     * @var Field[]
     */
    private array $fields = [];

    public function field(string $name): Field
    {
        if (!isset($this->fields[$name])) {
            $this->fields[$name] = new Field($name);
        }

        return $this->fields[$name];
    }

    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return $this->fields;
    }

    // -------------------------------------------------------------------------

    public function getValue(string $field): mixed
    {
        return $this->field($field)->getValue();
    }

    public function getError(string $field): string
    {
        return $this->field($field)->getError();
    }

    public function getMessage(string $field): mixed
    {
        return $this->field($field)->getMessage();
    }

    // -------------------------------------------------------------------------

    /**
     * @param array $data
     * @return boolean
     */
    public function validate(array $data): bool
    {
        foreach ($this->fields as $name => $field) {
            $field->validate($data[$name] ?? null);
        }

        return $this->isValid();
    }

    /**
     * @return boolean
     */
    public function isValid(): bool
    {
        foreach ($this->fields as $field) {
            if (!$field->isValid()) {
                return false;
            }
        }
        return true;
    }

    // -------------------------------------------------------------------------

    public function jsonSerialize(): mixed
    {
        $data = [];
        foreach ($this->fields as $name => $field) {
            $data[$name] = $field->getValue();
        }

        return $data;
    }

    // -------------------------------------------------------------------------
}
