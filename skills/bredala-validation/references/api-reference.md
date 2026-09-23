# bredala-validation — API cheat sheet

Quick lookup by intent. Read the source in `vendor/sugatasei/bredala-validation/src/` for anything not covered here.

## Schema factories (`Bredala\Validation\Schema`, static)

| Intent | Method | Built-in sanitizing / check | Codes |
| ------ | ------ | --------------------------- | ----- |
| Single-line string | `input(): StringType` | `StringFilter::input` | `type` |
| Multiline string | `text(): StringType` | `StringFilter::text` | `type` |
| String as sent | `string(): StringType` | `StringFilter::raw` (only `''` → `null`) | `type` |
| Integer | `int(): NumberType` | `IntegerFilter::sanitize` (truncates floats) | `type` |
| Float | `float(): NumberType` | `DecimalFilter::sanitize` | `type` |
| Boolean | `bool(): Type` | `BooleanFilter::sanitize` (numerics via `(int)` then `1/0`, `on/off`, `yes/no`, `true/false`) | `type` |
| Anything, unconverted | `mixed(): Type` | none | — |
| Date | `date(string $format = 'Y-m-d'): StringType` | `input` + strict format | `date` |
| Date and time | `datetime(?string $format = null): StringType` | `input` + ISO 8601 with offset by default (`…+02:00` or `…Z`) | `datetime` |
| Time | `time(): StringType` | `input` + `H:i` or `H:i:s` | `time` |
| Email | `email(): StringType` | `input` + `FILTER_VALIDATE_EMAIL` | `email` |
| http(s) URL | `url(): StringType` | `input` + `FILTER_VALIDATE_URL` + scheme `http`/`https` | `url` |
| UUID | `uuid(): StringType` | `input` + canonical 8-4-4-4-12, any version, case-insensitive | `uuid` |
| IP address | `ip(?int $version = null): StringType` | `input` + `FILTER_VALIDATE_IP` (`4`/`6` to restrict) | `ip` |
| JSON document | `json(): StringType` | `string` (**not cleaned**) + `json_validate()` | `json` |
| Backed enum | `enum(string $class): AnyOf` | `anyOf(...values)->castTo($class)` | `anyOf` |
| One of values or schemas | `anyOf(mixed ...$variants): AnyOf` | literals compared as trimmed strings | `anyOf`, or the closest schema variant's errors |
| Known keys | `structure(array $items): Structure` | `ArrayFilter::sanitize` | `type` |
| Same schema per element | `arrayOf(Schema $item, ?Schema $key = null): ArrayOf` | `ArrayFilter::sanitize` | `type`, `key` |
| Same, keys 0..n | `listOf(Schema $item): ArrayOf` | | `list` |
| Structure from a class | `from(string $class, array $items = []): Structure` | see below | |

## Schema (`Bredala\Validation\Schema`) — on every element

| Intent | Method |
| ------ | ------ |
| Reject a missing value (code `required`) | `required(bool $required = true): static` |
| Value when missing | `default(mixed $value): static` |
| Preprocess the raw input (before sanitizing) | `before(callable $callback): static` |
| Custom check on the sanitized value; falsy → `$code` | `assert(callable $callback, string $code = 'assert'): static` |
| Convert the valid value, for `data()` only | `transform(callable $callback): static` |
| Type in `data()`: `'int'`, `'float'`, `'string'`, `'bool'`, `'array'`, `'object'`, a backed enum (`tryFrom`, code `type` if unknown), a class | `castTo(string $type): static` |

`transform()` and `castTo()` skip `null`. `castTo(Class)` does `new Class($value)`, except on a structure: named constructor arguments, or property assignment without a constructor. Unknown types and pure enums throw `InvalidArgumentException` at declaration.

## Built-in checks (shortcuts to `Rules\*`)

Each shortcut declares a rule under its code; rules run in declaration order, before `assert()`, never on a missing value. Declaring a shortcut again replaces its rule.

| Element | Method | Rule | Codes |
| ------- | ------ | ---- | ----- |
| `StringType` (`input()`…`url()`) | `min(int $min)` / `max(int $max)` | `StringRule::minLength/maxLength` (chars, `mb_strlen`) | `min`, `max` |
| `StringType` | `length(int $length)` | `StringRule::length` (exact, chars) | `length` |
| `StringType` | `alpha()` / `alnum()` | `StringRule::isAlpha/isAlnum` (Unicode letters, + digits) | `alpha`, `alnum` |
| `StringType` | `digits()` | `StringRule::isDigits` (ASCII digits, keeps leading zeros) | `digits` |
| `StringType` | `pattern(string $pattern)` | `StringRule::matches` (whole string, no delimiters, Unicode) | `pattern` |
| `NumberType` (`int()`, `float()`) | `min(int\|float)` / `max(int\|float)` | `NumberRule::min/max` (inclusive) | `min`, `max` |
| `NumberType` | `multipleOf(int\|float $step)` | `NumberRule::isMultipleOf` (from 0, float-rounding tolerant) | `multipleOf` |
| `ArrayOf` | `min(int)` / `max(int)` | `ArrayRule::minCount/maxCount` | `min`, `max` |
| `ArrayOf` | `unique()` | `ArrayRule::isUnique` (strict, sanitized values); runs as an `assert()` | `unique` |
| `Structure` | `same($field, $other)` / `different($field, $other)` | strict comparison, a structure `assert()`, error on `$field` | `same`, `different` |

`Type` (`bool()`, `mixed()`) has no shortcut: use `assert()` with a rule.

## Structure (`Bredala\Validation\Elements\Structure`)

| Intent | Method |
| ------ | ------ |
| Cross-field check on all sanitized values (runs only when all items are valid); error on `$field`, or on the structure | `assert(callable $callback, string $code = 'assert', ?string $field = null): static` |
| The item schemas | `items(): array` |

Missing keys are processed as `null`, unknown keys dropped, an absent structure processed as `[]`.

## ArrayOf (`Bredala\Validation\Elements\ArrayOf`)

| Intent | Method | Codes |
| ------ | ------ | ----- |
| Minimum / maximum number of elements | `min(int $min): static` / `max(int $max): static` | `min`, `max` |

Defaults to `[]`; `required()` rejects `[]`. Keys preserved; element errors keyed by element key.

## Schema::from()

| Parameter type | Schema |
| -------------- | ------ |
| `string` | `input()` |
| `int` / `float` / `bool` | `int()` / `float()` / `bool()` |
| `array` | `arrayOf(mixed())` |
| `mixed` / untyped | `mixed()` |
| backed enum | `enum()` |
| other class | `from()` of that class |
| `DateTimeInterface`, union types | must be given in `$items`, otherwise `InvalidArgumentException` |

A default value becomes `default()`. No default and not nullable → `required()`, except arrays and classes. Uses constructor parameters, or public properties without a constructor.

## Validation / Result

| Intent | Method |
| ------ | ------ |
| Run a schema | `$schema->validate(mixed $data): Result` |
| Verdict | `Result::isValid(): bool` |
| Sanitized values (always available) | `Result::values(): mixed` |
| Error codes shaped like the data (`''` key for a root code) | `Result::errors(): array` |
| The raw error: own code, children errors, or `null` | `Result::error(): string\|array\|null` |
| Values after transform/castTo (`LogicException` if invalid) | `Result::data(): mixed` |

## Messages (`Bredala\Validation\Messages`)

| Intent | Method |
| ------ | ------ |
| New instance | `static create(): static` |
| Codes → messages at a key (`'*'` = any key) | `field(string $name, array $messages): static` |
| Messages for the errors below a key (`'*'` = any key) | `nested(string $name, Messages $messages): static` |
| Translate `Result::errors()`, keeping its shape | `parse(array $errors): array` |

Lookup per code: key's code → key's `'default'` → `'*'` code → `'*'` `'default'` → raw code.

## Filters (`Bredala\Validation\Filters\*`) — value in, value out; usable in `before()`/`transform()`

- `StringFilter::input()`, `text()`, `raw()`, `sanitize(mixed, bool $multiline)`, `stripTags(string)`, `sanitizeUrl()`, `sanitizeEmail()`, `sanitizeType(mixed, int $filter)`
- `IntegerFilter::sanitize()`, `DecimalFilter::sanitize()`, `BooleanFilter::sanitize()`
- `ArrayFilter::sanitize()`, `map(mixed, callable)`, `mapInput()`, `mapText()`, `mapInteger()`, `mapDecimal()`, `mapBoolean()`, `mapArray()`

## Rules (`Bredala\Validation\Rules\*`) — value in, bool out; usable in `assert()`

- `StringRule::minLength(string, int)`, `maxLength(string, int)`, `length(string, int)` — in characters.
- `StringRule::isAlpha(string)`, `isAlnum(string)`, `isDigits(string)`.
- `StringRule::isUuid(string)`, `isIp(string, ?int $version = null)`, `isJson(string)`.
- `StringRule::matches(string $value, string $pattern)` — whole string, no delimiters, Unicode.
- `StringRule::isDate(string $value, string ...$formats)` — strict date check.
- `StringRule::isEmail(string)`, `isUrl(string)` (http/https only).
- `NumberRule::min(int|float, int|float)`, `max(int|float, int|float)` — inclusive; `isMultipleOf(int|float, int|float)`.
- `ArrayRule::minCount(array, int)`, `maxCount(array, int)`, `isList(array)`, `isUnique(array)`.

## Rejecting

- `Schema::fail(string $code): never` — throws `Bredala\Validation\ValidationException` whose message is the code.
