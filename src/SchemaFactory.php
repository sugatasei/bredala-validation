<?php

namespace Bredala\Validation;

use BackedEnum;
use Bredala\Validation\Elements\AnyOf;
use Bredala\Validation\Elements\ArrayOf;
use Bredala\Validation\Elements\Structure;
use Bredala\Validation\Elements\Type;
use Bredala\Validation\Filters\BooleanFilter;
use Bredala\Validation\Filters\DecimalFilter;
use Bredala\Validation\Filters\IntegerFilter;
use Bredala\Validation\Filters\StringFilter;
use Bredala\Validation\Rules\StringRule;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

/**
 * The Schema::input(), Schema::structure()... factories.
 */
trait SchemaFactory
{
    // -------------------------------------------------------------------------
    // Strings
    // -------------------------------------------------------------------------

    /**
     * A single-line string, cleaned by StringFilter::input().
     */
    public static function input(): Type
    {
        return new Type([StringFilter::class, 'input']);
    }

    /**
     * A multiline string, cleaned by StringFilter::text().
     */
    public static function text(): Type
    {
        return new Type([StringFilter::class, 'text']);
    }

    /**
     * A string as sent, not cleaned: only '' becomes null.
     */
    public static function string(): Type
    {
        return new Type([StringFilter::class, 'raw']);
    }

    /**
     * A date, 'Y-m-d' by default (like <input type="date">).
     */
    public static function date(string $format = 'Y-m-d'): Type
    {
        return self::input()->format(fn(string $v) => StringRule::isDate($v, $format), 'date');
    }

    /**
     * An ISO 8601 date and time with its offset by default
     * (2026-10-15T14:30:00+02:00 or 2026-10-15T14:30:00Z).
     */
    public static function datetime(?string $format = null): Type
    {
        $formats = $format === null ? ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:sp'] : [$format];

        return self::input()->format(fn(string $v) => StringRule::isDate($v, ...$formats), 'datetime');
    }

    /**
     * A time as hh:mm or hh:mm:ss.
     */
    public static function time(): Type
    {
        return self::input()->format(fn(string $v) => StringRule::isDate($v, 'H:i', 'H:i:s'), 'time');
    }

    public static function email(): Type
    {
        return self::input()->format(fn(string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false, 'email');
    }

    /**
     * An http or https URL.
     */
    public static function url(): Type
    {
        return self::input()->format(
            fn(string $v) => filter_var($v, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string) parse_url($v, PHP_URL_SCHEME)), ['http', 'https'], true),
            'url'
        );
    }

    // -------------------------------------------------------------------------
    // Other scalars
    // -------------------------------------------------------------------------

    public static function int(): Type
    {
        return new Type([IntegerFilter::class, 'sanitize']);
    }

    public static function float(): Type
    {
        return new Type([DecimalFilter::class, 'sanitize']);
    }

    public static function bool(): Type
    {
        return new Type([BooleanFilter::class, 'sanitize']);
    }

    /**
     * Any value, not converted: only the before() callbacks apply.
     */
    public static function mixed(): Type
    {
        return new Type();
    }

    /**
     * One of the values of a backed enum: values() holds the value ('paid'),
     * data() the case (Status::Paid).
     *
     * @param class-string<BackedEnum> $class
     */
    public static function enum(string $class): AnyOf
    {
        if (!is_subclass_of($class, BackedEnum::class)) {
            throw new InvalidArgumentException("'{$class}' is not a backed enum");
        }

        return self::anyOf(...array_map(fn(BackedEnum $case) => $case->value, $class::cases()))
            ->castTo($class);
    }

    // -------------------------------------------------------------------------
    // Compositions
    // -------------------------------------------------------------------------

    /**
     * One of several literal values or schemas, tried in order.
     */
    public static function anyOf(mixed ...$variants): AnyOf
    {
        return new AnyOf(...$variants);
    }

    /**
     * @param Schema[] $items
     */
    public static function structure(array $items): Structure
    {
        return new Structure($items);
    }

    /**
     * An array whose elements (and optionally keys) match a schema.
     */
    public static function arrayOf(Schema $item, ?Schema $key = null): ArrayOf
    {
        return new ArrayOf($item, $key);
    }

    /**
     * Like arrayOf(), but the keys must be 0, 1, 2...
     */
    public static function listOf(Schema $item): ArrayOf
    {
        return new ArrayOf($item, list: true);
    }

    /**
     * A structure built from the constructor parameters of a class (or its
     * public properties), cast to that class. $items overrides or completes
     * the generated schemas.
     *
     * @param Schema[] $items
     */
    public static function from(string $class, array $items = []): Structure
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $members = $constructor
            ? $constructor->getParameters()
            : $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $schemas = [];
        foreach ($members as $member) {
            $name = $member->getName();
            $schemas[$name] = $items[$name] ?? self::fromMember($class, $member);
        }

        return self::structure($schemas + $items)->castTo($class);
    }

    private static function fromMember(string $class, ReflectionParameter|ReflectionProperty $member): Schema
    {
        $type = $member->getType();
        $name = $member->getName();

        if ($type !== null && !$type instanceof ReflectionNamedType) {
            throw new InvalidArgumentException("{$class}::\${$name}: union types need an explicit schema");
        }

        $typeName = $type?->getName() ?? 'mixed';

        $schema = match (true) {
            $typeName === 'string' => self::input(),
            $typeName === 'int' => self::int(),
            $typeName === 'float' => self::float(),
            $typeName === 'bool' => self::bool(),
            $typeName === 'array' => self::arrayOf(self::mixed()),
            $typeName === 'mixed' => self::mixed(),
            is_subclass_of($typeName, BackedEnum::class) => self::enum($typeName),
            class_exists($typeName) && !is_a($typeName, \DateTimeInterface::class, true)
                => self::from($typeName),
            default => throw new InvalidArgumentException(
                "{$class}::\${$name}: type {$typeName} needs an explicit schema"
            ),
        };

        $hasDefault = $member instanceof ReflectionParameter
            ? $member->isDefaultValueAvailable()
            : $member->hasDefaultValue();

        if ($hasDefault) {
            $schema->default($member->getDefaultValue());
        } elseif ($type !== null && !$type->allowsNull()
            && !$schema instanceof Structure && !$schema instanceof ArrayOf) {
            $schema->required();
        }

        return $schema;
    }
}
