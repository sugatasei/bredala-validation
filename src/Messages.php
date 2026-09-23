<?php

namespace Bredala\Validation;

/**
 * Turns error codes into messages. Mirrors the shape of the errors: field()
 * for the codes of a key, nested() for the errors below a key, '*' for any key
 * (the elements of arrayOf() / listOf()).
 */
class Messages
{
    private array $fields = [];
    private array $nested = [];

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

    public function nested(string $name, Messages $messages): static
    {
        $this->nested[$name] = $messages;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Generate messages from error codes
    // -------------------------------------------------------------------------

    public function parse(array $errors): array
    {
        $messages = [];
        foreach ($errors as $key => $error) {
            $messages[$key] = is_array($error)
                ? ($this->nested[$key] ?? $this->nested['*'] ?? new static())->parse($error)
                : $this->message((string) $key, $error);
        }

        return $messages;
    }

    /**
     * The field's code, then the field's 'default', then the same two for '*',
     * then the raw code.
     */
    private function message(string $key, string $error): string
    {
        return $this->fields[$key][$error]
            ?? $this->fields[$key]['default']
            ?? $this->fields['*'][$error]
            ?? $this->fields['*']['default']
            ?? $error;
    }

    // -------------------------------------------------------------------------
}
