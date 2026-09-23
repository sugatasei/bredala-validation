---
name: bredala-validation
description: How to correctly validate and sanitize form input — schemas, types, required/default, before/assert/transform/castTo, nested structures and arrays (any depth), anyOf/enum, DTOs via Schema::from, error codes and human-readable messages — using the sugatasei/bredala-validation PHP library (namespace Bredala\Validation — Schema (static factories + validate()), Result, Messages, Elements\Type/Structure/ArrayOf/AnyOf, and the Filters\* (StringFilter, IntegerFilter, DecimalFilter, BooleanFilter, ArrayFilter), the Rules\* (StringRule) and Schema::fail()). Its API is modeled on Nette Schema but keeps form-oriented sanitizing, error codes and values() for re-filling forms. Use this whenever the project's composer.json requires sugatasei/bredala-validation, code imports from Bredala\Validation\*, or you're asked to add/change input validation, a form or request validator, a DTO built from a payload, field sanitizing, required/min/max/pattern/date/email checks, or validation error messages in a PHP project that has this library — even if the request is phrased generically like "validate this payload", "make email required" or "check the age is between 18 and 99". Also check this before writing raw filter_var/preg_match/trim chains or a hand-rolled errors array in such a project, and before porting code from the v5 Form API (Form, StringFilter::required, Field::skip), which no longer exists.
---

# bredala-validation

`sugatasei/bredala-validation` (v6, PHP 8.5+) validates form data with schemas in the style of Nette Schema: describe the expected shape with the static factories of `Schema` (`Schema::input()`, `Schema::structure()`…), call `$schema->validate($data)`, then read the `Result`:

- `values()` — sanitized values, **available even when invalid**, to re-fill the HTML form;
- `errors()` — error **codes** (`required`, `min`…) shaped like the data;
- `data()` — the values after `transform()`/`castTo()` (DTOs, enums, dates); throws unless valid.

`Messages` turns codes into text. No HTTP handling, CSRF or upload checks.

Source: `vendor/sugatasei/bredala-validation/src/`. Read it for exact signatures; this skill covers how the pieces fit and what isn't obvious.

For the full method list see `references/api-reference.md`. Read `references/gotchas.md` before debugging a value or an error that "came back wrong".

## The pipeline (per element)

1. `before()` callbacks on the **raw** input.
2. The type's **built-in sanitizing** (`Schema::int()` turns `'42'` into `42`, `Schema::input()` trims and strips tags). `''` becomes `null`. Unconvertible input → code `type`.
3. `null` → code `required` if `required()`, otherwise the default. Steps 4–5 are skipped.
4. Built-in checks: `min`, `max`, `pattern`, date/email/url formats.
5. `assert()` callbacks. **The value now goes to `values()`.**
6. Only if valid: `transform()` callbacks, then `castTo()`. **The result goes to `data()`.**

So: sanitizing and validating affect `values()`; rich conversions (string → `DateTimeImmutable`, value → enum, array → DTO) go in `transform()`/`castTo()` and never touch `values()`.

## Core recipe

```php
use Bredala\Validation\Schema;
use Bredala\Validation\Messages;

$schema = Schema::structure([
    'email'  => Schema::email()->required()->transform('mb_strtolower'),
    'name'   => Schema::input()->required()->min(2)->max(50),
    'age'    => Schema::int()->min(18)->max(130),                 // optional by default
    'status' => Schema::enum(Status::class)->required(),         // values(): 'paid', data(): Status::Paid
    'start'  => Schema::date()->required()->castTo(DateTimeImmutable::class),
    'end'    => Schema::date()->required()->castTo(DateTimeImmutable::class),
    'lines'  => Schema::arrayOf(Schema::structure([
        'sku' => Schema::input()->required(),
        'qty' => Schema::int()->required()->min(1),
    ]))->min(1),
])->assert(fn(array $v) => $v['end'] >= $v['start'], 'before_start', 'end');   // cross-field, error on 'end'

$result = $schema->validate($_POST);

if ($result->isValid()) {
    $data = $result->data();
} else {
    $values = $result->values();                                  // re-fill the form
    $messages = Messages::create()
        ->field('*', ['required' => 'Ce champ est obligatoire'])
        ->nested('lines', Messages::create()->nested('*', Messages::create()
            ->field('qty', ['min' => 'Au moins 1'])))
        ->parse($result->errors());
}
```

## Choosing a type

| Need | Use |
| ---- | --- |
| Single-line text (cleaned: tags stripped, whitespace collapsed, trimmed) | `Schema::input()` |
| Textarea (keeps line breaks) | `Schema::text()` |
| A string exactly as sent (only `''` → `null`) — e.g. a password, or as a base for your own `before()` | `Schema::string()` |
| Anything, unconverted | `Schema::mixed()` + `before()` |
| Numbers / booleans from a form | `Schema::int()`, `Schema::float()`, `Schema::bool()` |
| Date / datetime / time (strict formats) | `Schema::date()`, `Schema::datetime()`, `Schema::time()` |
| Email / http(s) URL | `Schema::email()`, `Schema::url()` |
| One of fixed values | `Schema::anyOf('a', 'b')`, or `Schema::enum(Status::class)` |
| A sub-object | `Schema::structure([...])` |
| Repeated rows / tags | `Schema::arrayOf($item)`, `Schema::listOf($item)` (keys 0..n) |
| Polymorphic item (card vs paypal) | `Schema::anyOf(Schema::structure(...), Schema::structure(...))` with a discriminant field |
| A DTO | `Schema::from(Dto::class, [overrides])` |

Prefer `input()` over `string()` for user-visible text; `string()` keeps tags and whitespace.

## Custom validation

```php
// one code
Schema::input()->assert(fn(string $v) => !in_array($v, ['admin', 'root']), 'reserved');

// several codes: throw, then return true
Schema::string()->required()->assert(function (string $v) {
    if (mb_strlen($v) < 12) Schema::fail('too_short');
    if (!preg_match('/\d/', $v)) Schema::fail('no_digit');
    return true;
});

// reusable type
function siret(): \Bredala\Validation\Elements\Type
{
    return Schema::input()
        ->before(fn($v) => str_replace(' ', '', (string) $v))
        ->pattern('\d{14}')
        ->assert(fn(string $v) => luhn($v), 'siret');
}
```

- `assert()` fails on a **falsy** return; forgetting `return true` in a multi-code callback fails every value.
- A structure's `assert($fn, $code, $field)` receives all its sanitized values, runs only when all its items are valid, and puts the error on `$field` (or on the structure itself without `$field`; under `''` at the root).
- `before()` / `transform()` can also reject with `Schema::fail('code')`.

## Errors and messages

Errors mirror the data. A leaf is a code; a structure or array holds its children's errors, keyed like the data, listing **only invalid entries**:

```php
['name' => 'required', 'address' => ['zip' => 'pattern'], 'lines' => [3 => ['qty' => 'min']], 'tags' => [1 => 'max']]
```

An element has **either** its own code **or** children errors. A root-level code is under the `''` key. `Result::error()` returns the raw error instead (`'email'` rather than `['' => 'email']`), handy when validating a single field.

`Messages` mirrors that shape: `field($key, [code => message])` for codes at a key, `nested($key, Messages)` for errors below a key, and `'*'` for any key (array elements). Lookup order per code: key's code → key's `'default'` → `'*'` code → `'*'` `'default'` → raw code.

## Behavior to keep in mind

- **Optional by default.** Absent, `null`, `''` and whitespace-only input (for `input()`) are all "missing". Use `required()`.
- **Unknown keys are silently dropped** from structures.
- **An absent structure is processed as `[]`**, so its children's `required()` still fire; an absent array defaults to `[]`.
- **An array's own error (`min`, `max`, `list`, `type`, `required`) hides its elements' errors.**
- **`data()` throws `LogicException` when invalid**; always check `isValid()` first. `values()` never throws.
- **After a `type` error, `values()` holds the default**, not the raw input.
- **`castTo()` on a structure uses named constructor arguments**: every declared key must match a parameter, or PHP throws `Error: Unknown named parameter`.
- **`IntegerFilter` truncates floats** (`'1.9'` → `1`) instead of erroring.
- **Tags are stripped but their text survives** in `input()`/`text()`: this is sanitizing, not XSS protection. Escape on output.
- **Dates are strict**: `2026-02-30`, `9:05` and `tomorrow` are rejected. `datetime()` defaults to ISO 8601 with offset; `<input type="datetime-local">` needs `datetime('Y-m-d\TH:i')`.

## Porting from v5 (`Form`)

`Form`, `SkipException` and the static rules (`StringField::min()`, `Field::required()`, `Field::skip()`, `Field::include()`…) are gone. Map a v5 form to `Schema::structure()` (or `Schema::from()`); `Field::skip()` becomes nothing (optional is the default); `range` becomes `min()`/`max()` (codes `min`/`max`, no more `range`); `Field::include()` becomes `anyOf()`/`enum()`; `$this->setValue()` in a rule becomes `before()` (for `values()`) or `transform()` (for `data()`); `$form->validate($data)` becomes `$schema->validate($data)`.
