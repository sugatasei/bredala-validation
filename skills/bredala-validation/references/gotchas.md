# bredala-validation — gotchas

Things the method names don't tell you. Most items are pinned by a test in `tests/`.

## Processing and results

- **`data()` throws `LogicException` on an invalid result**, including one invalid only because a `transform()` rejected the value. Check `isValid()` first. `values()` and `errors()` never throw.
- **`values()` is the sanitized value, not the raw input.** After a `type` error, it holds the element's **default** (usually `null`), so a form re-filled from `values()` loses what the user typed in that field.
- **`values()` of a structure always has every declared key**, even when the input lacked them, and never has undeclared keys.
- **A root-level error is under the `''` key** of `errors()`: `type` when the data isn't an array, or a root `assert()` without `$field`.
- **A schema holds no state between runs**, so one instance can be processed many times, or shared.

## required / default / empty

- **Everything is optional by default.** `null`, `''`, and anything the sanitizer turns into `null` (whitespace-only for `input()`/`text()`, `[]` for arrays) count as missing.
- **`Schema::string()` only treats `''` as missing**: `'   '` is a real value and passes `required()`.
- **`Schema::mixed()` doesn't even turn `''` into `null`**: `''` passes `required()`.
- **Checks and asserts don't run on a missing value**, so `Schema::input()->min(3)` accepts an absent field. Add `required()`.
- **`transform()` and `castTo()` do run on a non-null default**: `Schema::int()->default(18)->castTo('string')` gives `'18'` in `data()`.
- **`required()` and `default()` have no effect on a structure**: an absent structure is processed as `[]`.
- **An absent array defaults to `[]`, but `min(1)` still fails it with `min`.**

## Types and sanitizing

- **`IntegerFilter` truncates floats** (`'1.9'` → `1`) instead of erroring, and accepts exponent notation (`'1e3'` → `1000`).
- **`Schema::float()` always yields a float**: `3` → `3.0`, so `=== 3` fails.
- **`Schema::bool()` rejects numbers other than 0 and 1** with `type`.
- **`input()`/`text()` strip tags but keep their text**: `'<script>alert(1)</script>'` → `'alert(1)'`. Entities are decoded (`'a&amp;b'` → `'a&b'`). Escape on output.
- **`input()`/`text()` short-circuit on numerics**: `42` becomes `'42'` with no cleaning.
- **`true` is a `type` error for every string type** (it isn't numeric).
- **`StringFilter::sanitizeEmail()`/`sanitizeUrl()` only clean, they don't validate**, and return `null` instead of erroring. Use `Schema::email()`/`url()` to validate.
- **`Schema::url()` rejects non-http(s) schemes**, including `javascript:` and `ftp:`.
- **`min()`/`max()` count characters, not bytes** (`mb_strlen`). Database limits are often in bytes.
- **`pattern()` is anchored to the whole string** and takes no delimiters: `'\d{5}'` rejects `'750011'`. It's compiled with the `u` flag.

## Dates

- **The date checks are strict round-trips**: parsed with `createFromFormat()` then formatted back, which must equal the input. `2026-02-30`, `25:00`, `9:05`, `2026-1-5`, `tomorrow` and a trailing `.123` are all rejected.
- **`datetime()`'s default needs seconds and an offset** (`+02:00` with a colon, or `Z`). `<input type="datetime-local">` sends `2026-10-15T14:30`: use `datetime('Y-m-d\TH:i')`.
- **Only the default formats are safe with `castTo(DateTimeImmutable::class)`**, which calls `new DateTimeImmutable($value)`. For `d/m/Y`, use `transform(fn($v) => DateTimeImmutable::createFromFormat('!d/m/Y', $v))`.
- **`Schema::from()` can't infer a date** from a `DateTimeImmutable` parameter; it throws until you provide the schema in `$items`.

## assert / before / transform

- **`assert()` fails on any falsy return**, so a multi-code callback must end with `return true`. A truthy non-bool (like `preg_match()`'s `1`) passes.
- **Asserts run after the built-in checks, in declaration order; the first failure wins.**
- **A structure's (or array's) `assert()` only runs when all its children are valid**, and receives the **sanitized** values (`values()` side), not the transformed/cast ones: dates are still strings there.
- **`before()` receives the raw input, including arrays and `null`**; guard your callback, or it may throw a PHP `TypeError` rather than an error code.
- **Only `ValidationException` becomes an error code.** Any other exception thrown in a callback (or by a DTO constructor) propagates.

## castTo

- **On a structure, a class constructor gets named arguments**: every declared key must match a parameter, or PHP throws `Error: Unknown named parameter $x`. A missing optional parameter falls back to its default. Types aren't converted: a `null` for a non-nullable parameter throws `TypeError`.
- **Without a constructor, properties are assigned one by one**: private, protected or readonly properties throw `Error`.
- **On anything else, `castTo(Class)` calls `new Class($value)`**, e.g. `ArrayObject` on an array.
- **A backed enum uses `tryFrom()`**: an unknown value becomes the code `type`, not a `ValueError`. `Schema::enum()` reports `anyOf` earlier, during validation.
- **Pure enums and unknown type names throw `InvalidArgumentException` at declaration.**

## Structures and arrays

- **Unknown keys are dropped silently**, never reported.
- **An array's own error hides its elements' errors.** With `max(3)` and 4 invalid rows, `errors()` is `['lines' => 'max']` until the count is fixed; `values()` still has every row.
- **A key rejected by `arrayOf()`'s key schema replaces that element's own error** with `key`.
- **`listOf()` requires `array_is_list()`**: form rows after a deletion (`[0 => …, 2 => …]`) fail with `list`. Use `arrayOf()` for HTML rows.
- **Keys are preserved** in `values()`, `errors()` and `data()`.

## anyOf / enum

- **Literals are compared as trimmed strings, case-sensitively**, and the value becomes the literal: `anyOf(1, 2)` turns `'2'` into the int `2`. Bool literals only match strictly.
- **Schema variants: the first valid one wins.** When none is valid, the errors are those of the variant with the fewest errors (the first on a tie); with a shared discriminant field (`type`), that's normally the intended variant.
- **An array never matches a literal**; its `values()` is `null` then.

## Schema::from

- **Arrays and nested classes are never `required()`**, even without a default: arrays default to `[]` and nested structures are processed as `[]`.
- **`array` parameters become `arrayOf(mixed())`**: the elements aren't validated. Override them in `$items`.
- **Overrides in `$items` for names that aren't parameters are added to the structure**, and then fail `castTo()` as unknown named arguments.

## Messages

- **The configuration mirrors the error shape.** For a list of structures you need `nested('lines', Messages::create()->nested('*', $lineMessages))`; `nested('lines', $lineMessages)` alone looks up `lineMessages` keys against the row indexes.
- **Scalar list items are translated with `field('*', …)`** inside the list's nested `Messages`.
- **A field's `'default'` wins over a `'*'` code.**
- **A child `Messages` doesn't inherit its parent's `'*'`.**
- **Nothing is ever dropped**: an unconfigured code comes out raw.
- **`field()` replaces the whole table** for that key.
