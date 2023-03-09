<?php

namespace Bredala\Validation;

class Messages
{
    private array $fields = [];

    // -------------------------------------------------------------------------

    public static function create(): static
    {
        return new static();
    }

    // -------------------------------------------------------------------------
    // Configure error messages
    // -------------------------------------------------------------------------

    public function field(string $name, array $messages): static
    {
        $this->fields[$name] = $messages;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Generate messages from error codes
    // -------------------------------------------------------------------------

    public function parse(array $errors): array
    {
        $messages = [];
        foreach ($errors as $field => $error) {
            $messages[$field] = $this->fields[$field][$error]
                ?? $this->fields[$field]['default']
                ?? $error;
        }

        return $messages;
    }

    // -------------------------------------------------------------------------
}
