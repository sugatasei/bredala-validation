# Bredala/Validation

Validation et nettoyage de données de formulaires pour PHP, avec une API inspirée de [Nette Schema](https://doc.nette.org/fr/schema).

On décrit la forme attendue des données avec les fabriques de `Schema` (`Schema::input()`, `Schema::structure()`...), on appelle `validate()`, puis on lit le résultat :

- `values()` : les valeurs nettoyées, **même si les données sont invalides**, pour préremplir le formulaire ;
- `errors()` : des **codes** d'erreur (`required`, `min`...), rangés comme les données ;
- `data()` : les valeurs converties (objets, enums, dates), une fois les données valides.

Pas de gestion HTTP, pas de CSRF, pas de contrôle d'upload.

## Installation

PHP 8.5 ou plus.

```bash
composer require sugatasei/bredala-validation
```

## Claude Code

Ce package fournit un skill Claude Code dans [`skills/bredala-validation/`](skills/bredala-validation/) qui documente les patterns d'usage et les pièges de la librairie.

Dans un projet qui dépend de `sugatasei/bredala-validation`, copie-le une fois dans `.claude/skills/` après `composer install` pour que Claude Code le charge automatiquement (le nom du dossier doit correspondre au `name` déclaré dans `SKILL.md`) :

```bash
cp -r vendor/sugatasei/bredala-validation/skills/bredala-validation .claude/skills/bredala-validation
```

## Exemple

```php
use Bredala\Validation\Schema;
use Bredala\Validation\Messages;

enum Status: string
{
    case Pending = 'pending';
    case Paid = 'paid';
}

$schema = Schema::structure([
    'name'   => Schema::input()->required()->max(50),
    'email'  => Schema::email()->required(),
    'age'    => Schema::int()->min(18),
    'status' => Schema::enum(Status::class)->required(),
    'date'   => Schema::date()->required()->castTo(DateTimeImmutable::class),
    'lines'  => Schema::arrayOf(Schema::structure([
        'sku' => Schema::input()->required(),
        'qty' => Schema::int()->required()->min(1),
    ]))->min(1),
]);

$result = $schema->validate($_POST);

if ($result->isValid()) {
    $booking = $result->data();
    // $booking['status'] === Status::Paid, $booking['date'] est une DateTimeImmutable
} else {
    $values = $result->values();   // pour préremplir les champs
    $errors = Messages::create()
        ->field('email', ['required' => "L'email est obligatoire", 'default' => 'Email invalide'])
        ->parse($result->errors());
}
```

## Principe

Chaque élément d'un schéma traite sa valeur en plusieurs étapes :

1. **`before()`** : tes fonctions, sur la donnée brute, dans l'ordre de déclaration.
2. **Nettoyage du type** : `Schema::int()` convertit `'42'` en `42`, `Schema::input()` rogne et retire les balises, etc. Une chaîne vide devient `null`. Une valeur inconvertible donne le code `type`.
3. **Valeur absente** (`null`) : code `required` si l'élément est `required()`, sinon la valeur par défaut. Les étapes 4 et 5 sont alors sautées.
4. **Contrôles** du type, dans l'ordre de déclaration : format de date, d'email..., `min()`, `max()`, `pattern()`...
5. **`assert()`** : tes règles.

   → la valeur obtenue va dans `values()`.

6. **`transform()`** puis **`castTo()`**, seulement si l'élément est valide.

   → le résultat va dans `data()`.

`values()` sert donc à réafficher le formulaire, et `data()` à passer des données valides et typées au reste de l'application. Les conversions « riches » (chaîne → date, valeur → enum, tableau → DTO) se déclarent avec `transform()` et `castTo()` : elles ne touchent jamais `values()`.

Les clés non déclarées dans une structure sont ignorées silencieusement.

## Types

| Type | Nettoyage intégré | `values()` pour `'  42 '` |
| ---- | ----------------- | ------------------------- |
| `Schema::input()` | une ligne : balises retirées, espaces normalisées, rognage (`StringFilter::input`) | `'42'` |
| `Schema::text()` | multiligne : garde au plus deux retours à la ligne consécutifs (`StringFilter::text`) | `'42'` |
| `Schema::string()` | **aucun** : conversion en chaîne seulement, `''` devient `null` (`StringFilter::raw`) | `'  42 '` |
| `Schema::int()` | `IntegerFilter::sanitize` | `42` |
| `Schema::float()` | `DecimalFilter::sanitize` | `42.0` |
| `Schema::bool()` | `BooleanFilter::sanitize` : `'1'`, `'on'`, `'yes'`, `'true'` → `true` | code `type` |
| `Schema::date($format = 'Y-m-d')` | `input`, puis format de date | code `date` |
| `Schema::datetime($format = null)` | `input`, puis ISO 8601 avec décalage par défaut | code `datetime` |
| `Schema::time()` | `input`, puis `hh:mm` ou `hh:mm:ss` | code `time` |
| `Schema::email()` | `input`, puis `FILTER_VALIDATE_EMAIL` | code `email` |
| `Schema::url()` | `input`, puis URL `http` ou `https` | code `url` |
| `Schema::uuid()` | `input`, puis UUID `8-4-4-4-12`, toute version, majuscules acceptées | code `uuid` |
| `Schema::ip($version = null)` | `input`, puis IPv4 ou IPv6 (`4` ou `6` pour restreindre) | code `ip` |
| `Schema::json()` | `string` (**aucun** nettoyage), puis `json_validate()` | code `json` |
| `Schema::mixed()` | **aucun**, pas même la conversion : seulement tes `before()` | `'  42 '` |
| `Schema::enum(Status::class)` | une des valeurs de l'enum, voir [anyOf et enum](#anyof-et-enum) | code `anyOf` |
| `Schema::structure([...])` | un tableau à clés connues, voir [Structures](#structures) | |
| `Schema::arrayOf($item, $key = null)` | un tableau d'éléments de même schéma, voir [Tableaux](#tableaux) | |
| `Schema::listOf($item)` | pareil, avec des clés `0, 1, 2...` | |
| `Schema::anyOf(...)` | une valeur ou un schéma parmi plusieurs | |
| `Schema::from(Classe::class)` | une structure déduite d'une classe, voir [Schema::from](#schemafrom) | |

`Schema::string()` ne nettoie rien : les balises et les espaces passent. Il sert de base à un `before()` qui fait son propre nettoyage, et il faut, comme toujours, échapper la valeur à l'affichage.

Les types de chaînes, de `input()` à `url()`, sont des `Elements\StringType` ; `int()` et `float()` des `Elements\NumberType` ; `bool()` et `mixed()` des `Elements\Type`. Leurs contrôles diffèrent, voir [Contrôles](#contrôles).

Les filtres et les règles sont détaillés dans [Filtres et règles](#filtres-et-règles).

## Options communes

Toutes les méthodes sont chaînables.

### `required()` et `default()`

```php
Schema::input()->required();          // absent ou vide → code required
Schema::int()->default(18);           // absent ou vide → 18
Schema::input()->required(false);     // annule un required()
```

Un élément est facultatif par défaut : absent, il vaut `null` (ou son défaut). Comme le nettoyage transforme `''` en `null`, un champ HTML laissé vide compte comme absent.

### `before(callable)`

S'exécute sur la donnée **brute**, avant le nettoyage du type :

```php
Schema::int()->before(fn($v) => str_replace(' ', '', (string) $v));   // '1 000' → 1000
Schema::input()->before(fn($v) => $v === 'n/a' ? null : $v);
Schema::string()->before(fn($v) => preg_replace('/\D/', '', (string) $v))->pattern('\d{10}');
```

Un `before()` peut rejeter la valeur avec un code : `Schema::fail('code')`.

### `assert(callable, string $code = 'assert')`

S'exécute sur la valeur **nettoyée**. Une valeur de retour fausse donne le code :

```php
Schema::input()->assert(fn(string $v) => !in_array($v, ['admin', 'root']), 'reserved');
```

Pour plusieurs codes possibles, lever le code avec `Schema::fail()` et retourner `true` :

```php
use Bredala\Validation\Schema;

Schema::input()->required()->assert(function (string $v) {
    if (mb_strlen($v) < 12) Schema::fail('too_short');
    if (!preg_match('/\d/', $v)) Schema::fail('no_digit');
    return true;
});
```

Les `assert()` s'exécutent dans l'ordre, après les contrôles intégrés. Le premier en échec l'emporte. Ils ne s'exécutent pas sur une valeur absente.

### `transform(callable)`

Convertit la valeur **valide**, pour `data()` uniquement :

```php
Schema::date('d/m/Y')
    ->transform(fn(string $v) => DateTimeImmutable::createFromFormat('!d/m/Y', $v));

Schema::input()->transform('mb_strtolower');
```

Plusieurs `transform()` s'enchaînent dans l'ordre, et peuvent rejeter la valeur avec `Schema::fail()`. Ils ne s'exécutent pas sur `null`.

### `castTo(string $type)`

Convertit la valeur dans `data()`, après les `transform()` :

| Type | Conversion |
| ---- | ---------- |
| `'int'`, `'float'`, `'string'`, `'bool'`, `'array'`, `'object'` | cast PHP (`(int) $v`...) |
| un enum adossé (`BackedEnum`) | `Enum::tryFrom($v)`, code `type` si la valeur est inconnue |
| une classe, sur une structure | le constructeur reçoit les valeurs en **arguments nommés** ; sans constructeur, les propriétés sont affectées une à une |
| une classe, sur un autre élément | `new Classe($v)` |

```php
Schema::date()->castTo(DateTimeImmutable::class);            // new DateTimeImmutable('2026-10-15')
Schema::input()->castTo(Status::class);                     // Status::tryFrom('paid')
Schema::structure([...])->castTo(Booking::class);           // new Booking(name: ..., date: ...)
Schema::arrayOf(Schema::int())->castTo(ArrayObject::class); // new ArrayObject([...])
```

Un type inconnu ou un enum pur lève `InvalidArgumentException` dès `castTo()`. `castTo()` ne s'applique pas à `null`.

## Contrôles

Les contrôles intégrés sont des raccourcis vers les [règles](#règles) : chaque raccourci déclare une règle et son code. Ils s'exécutent dans l'ordre de déclaration, avant les `assert()`, et ne s'exécutent pas sur une valeur absente. Redéclarer un raccourci remplace sa règle : `->max(10)->max(20)` garde `max(20)`.

Les raccourcis dépendent du type :

| Type | Raccourci | Règle | Code |
| ---- | --------- | ----- | ---- |
| chaînes : `input()`, `text()`, `string()`, `date()`, `email()`... | `min(int)`, `max(int)` | `StringRule::minLength()`, `maxLength()` : des **caractères** (`mb_strlen`), pas des octets | `min`, `max` |
| | `length(int)` | `StringRule::length()` : longueur exacte | `length` |
| | `alpha()`, `alnum()` | `StringRule::isAlpha()`, `isAlnum()` : lettres (accentuées comprises), et chiffres pour `alnum()` | `alpha`, `alnum` |
| | `digits()` | `StringRule::isDigits()` : chiffres ASCII seulement, zéros en tête conservés (`'0612'`) ; refuse `'-1'` et `'1.5'` | `digits` |
| | `pattern(string)` | `StringRule::matches()` : une expression régulière sans délimiteurs, qui doit correspondre à **toute** la chaîne (`'\d{5}'` refuse `'750011'`). Unicode activé. | `pattern` |
| nombres : `int()`, `float()` | `min(int\|float)`, `max(int\|float)` | `NumberRule::min()`, `max()`, bornes incluses | `min`, `max` |
| | `multipleOf(int\|float)` | `NumberRule::isMultipleOf()` : comme l'attribut `step`, compté depuis 0 ; `0.3` est un multiple de `0.1` malgré l'arrondi | `multipleOf` |
| tableaux : `arrayOf()`, `listOf()` | `min(int)`, `max(int)` | `ArrayRule::minCount()`, `maxCount()` | `min`, `max` |
| | `unique()` | `ArrayRule::isUnique()` : comparaison stricte des valeurs nettoyées ; s'exécute comme un `assert()`, donc seulement si tous les éléments sont valides | `unique` |
| structures | `same($field, $other)`, `different($field, $other)` | `$field` égal (ou différent) à `$other`, comparaison stricte ; un `assert()` de structure, erreur sur `$field` | `same`, `different` |

`bool()` et `mixed()` n'ont pas de raccourci : utiliser `assert()` avec une règle.

### Dates et heures

- `Schema::date($format = 'Y-m-d')` : le format par défaut est celui de `<input type="date">`. Code : `date`.
- `Schema::datetime($format = null)` : par défaut, ISO 8601 avec décalage, `2026-10-15T14:30:00+02:00` ou `2026-10-15T14:30:00Z`. Pour `<input type="datetime-local">`, qui envoie `2026-10-15T14:30`, passer `'Y-m-d\TH:i'`. Code : `datetime`.
- `Schema::time()` : `14:30` ou `14:30:15`. Code : `time`.

Ces contrôles sont **stricts** : la valeur est lue avec `DateTimeImmutable::createFromFormat()` puis réécrite dans le même format, et les deux doivent être identiques. Sont donc refusés les dates débordantes (`2026-02-30`, `25:00`, `14:60`), les zéros manquants (`2026-1-5`, `9:05`) et les formats relatifs (`tomorrow`), que `strtotime()` et `new DateTimeImmutable()` acceptent.

La valeur reste une chaîne dans `values()`. Avec les formats par défaut, `castTo(DateTimeImmutable::class)` la convertit sans erreur. Avec un format personnalisé comme `d/m/Y`, utiliser `transform()` et `createFromFormat()`.

### Email et URL

- `Schema::email()` : `FILTER_VALIDATE_EMAIL`. Code : `email`.
- `Schema::url()` : `FILTER_VALIDATE_URL`, et seulement les schémas `http` et `https` (`javascript:` est refusé). Code : `url`.

## Structures

```php
$schema = Schema::structure([
    'name'    => Schema::input()->required(),
    'address' => Schema::structure([
        'city' => Schema::input()->required(),
        'zip'  => Schema::input()->pattern('\d{5}'),
    ]),
]);
```

- Chaque clé est traitée par son schéma. Une clé absente est traitée comme `null`.
- Les clés non déclarées sont ignorées.
- Une structure absente (`null`, `''`, `[]`) est traitée comme `[]` : ses défauts et ses `required()` s'appliquent quand même. `required()` n'a donc pas d'effet sur une structure.
- Avec `default()`, la structure devient facultative : absente, elle prend cette valeur sans traiter ses champs. `Schema::structure([...])->default(null)` accepte une adresse absente, mais valide une adresse fournie.
- Une valeur qui n'est pas un tableau donne le code `type` pour la structure.
- `data()` est un tableau, sauf `castTo()`.

### Règle sur plusieurs champs

`assert()` sur une structure reçoit toutes ses valeurs nettoyées. Le troisième argument indique le champ qui recevra l'erreur, pour l'afficher à côté du bon input :

```php
Schema::structure([
    'start' => Schema::date()->required(),
    'end'   => Schema::date()->required(),
])->assert(fn(array $v) => $v['end'] >= $v['start'], 'before_start', 'end');

// errors() : ['end' => 'before_start']
```

- L'`assert()` d'une structure ne s'exécute que si tous ses champs sont valides : pas de `null` inattendu à gérer.
- Il peut aussi lever son propre code avec `Schema::fail()` : le code va sur le champ indiqué, ou sur la structure.
- Sans champ, le code va sur la structure elle-même (`['period' => 'order']`). Pour la structure racine, il est sous la clé `''`.
- Il reçoit les valeurs de `values()`, pas celles de `data()` : des chaînes pour les dates, par exemple.

Pour les cas courants, `same()` et `different()` sont des raccourcis :

```php
Schema::structure([
    'password' => Schema::string()->required()->min(12),
    'confirm'  => Schema::string()->required(),
])->same('confirm', 'password');   // errors() : ['confirm' => 'same']
```

### Réutiliser un schéma

Un schéma est un objet ordinaire. Une fonction qui en retourne un sert de type personnalisé :

```php
use Bredala\Validation\Elements\StringType;

function siret(): StringType
{
    return Schema::input()
        ->before(fn($v) => str_replace(' ', '', (string) $v))
        ->pattern('\d{14}')
        ->assert(fn(string $v) => luhn($v), 'siret');
}

'siret' => siret()->required(),
```

Un même schéma peut être traité plusieurs fois : il ne garde aucun état entre deux appels.

## Tableaux

```php
Schema::arrayOf(Schema::input()->max(20))->max(10);    // clés quelconques, conservées
Schema::listOf(Schema::int());                         // clés 0, 1, 2... sinon code list
Schema::arrayOf(Schema::int(), Schema::input()->pattern('[a-z]+'));   // valide aussi les clés
Schema::arrayOf(Schema::structure([...]))->min(1);     // lignes d'une commande
```

- Chaque élément est traité par le même schéma, à n'importe quelle profondeur. Les clés sont conservées.
- Un tableau absent vaut `[]`, pour qu'un DTO typé `array` ne reçoive jamais `null`. `required()` refuse un tableau vide.
- `min()` et `max()` comptent les éléments. Codes : `min`, `max`. Un tableau absent compte zéro élément : `min(1)` le refuse, même sans `required()`.
- Une clé refusée par le schéma de clé donne le code `key` pour cet élément.
- Une valeur qui n'est pas un tableau donne le code `type`.

Les erreurs sont indexées par clé, et seuls les éléments invalides y figurent. Avec `Schema::arrayOf(Schema::input()->max(3))` et `['abc', 'abcd', 'ab', 'abcde']`, `errors()` vaut `[1 => 'max', 3 => 'max']`.

Une erreur du tableau lui-même (`min`, `max`, `list`, `type`, `required`) remplace les erreurs des éléments : il faut d'abord corriger le nombre d'éléments. `values()` contient quand même les éléments nettoyés.

`assert()` sur un tableau reçoit tous ses éléments nettoyés, et ne s'exécute que si tous sont valides :

```php
Schema::arrayOf(Schema::int())->assert(fn(array $v) => count(array_unique($v)) === count($v), 'unique');
```

## anyOf et enum

`Schema::anyOf()` accepte une valeur parmi une liste de valeurs littérales ou de schémas, essayés dans l'ordre.

Avec des **valeurs**, la saisie d'un formulaire est comparée en chaîne, sans les espaces autour. La valeur devient alors la valeur littérale, avec son type :

```php
Schema::anyOf('draft', 'paid');   // ' paid ' → 'paid'
Schema::anyOf(1, 2, 3);           // '2' → 2
```

La comparaison respecte la casse, et un booléen littéral n'est accepté que strictement. Code : `anyOf`.

Seul `''` compte comme absent : une saisie faite d'espaces donne le code `anyOf`, même sans `required()`.

Avec des **schémas**, le premier qui valide la valeur l'emporte :

```php
Schema::anyOf('auto', Schema::int()->min(1));   // 'auto' ou un entier

Schema::anyOf(
    Schema::structure(['type' => Schema::anyOf('card')->required(),   'number' => Schema::input()->required()]),
    Schema::structure(['type' => Schema::anyOf('paypal')->required(), 'email'  => Schema::email()->required()]),
);
```

Si aucun schéma ne valide la valeur, `errors()` contient les erreurs du schéma qui en a **le moins**, et non le code `anyOf` (le premier en cas d'égalité). Avec un champ `type` qui départage les variantes, ce sont donc les erreurs de la bonne variante : `['type' => 'paypal', 'email' => 'bad']` donne `['email' => 'email']`.

`Schema::enum()` est un `anyOf()` des valeurs d'un enum adossé, avec `castTo()` vers l'enum :

```php
Schema::enum(Status::class);   // values() : 'paid'   data() : Status::Paid
```

## Schema::from

`Schema::from()` construit une structure à partir des paramètres du constructeur d'une classe (ou de ses propriétés publiques sans constructeur), avec `castTo()` vers cette classe :

```php
final class Booking
{
    public function __construct(
        public readonly string $name,
        public readonly Status $status,
        public readonly Address $address,
        public readonly int $guests = 2,
        public readonly ?string $note = null,
        public readonly array $lines = [],
    ) {}
}

$schema = Schema::from(Booking::class, [
    'lines' => Schema::arrayOf(Schema::from(Line::class))->min(1),
]);

$booking = $schema->validate($_POST)->data();   // Booking
```

| Type du paramètre | Schéma |
| ----------------- | ------ |
| `string` | `Schema::input()` |
| `int`, `float`, `bool` | `Schema::int()`, `float()`, `bool()` |
| `array` | `Schema::arrayOf(Schema::mixed())` |
| `mixed` ou sans type | `Schema::mixed()` |
| un enum adossé | `Schema::enum()` |
| une autre classe | `Schema::from()` de cette classe |
| une date, un type union | **à fournir** dans le deuxième argument, sinon `InvalidArgumentException` |

- Un paramètre avec une valeur par défaut prend ce défaut. Pour un enum, `values()` contient la valeur (`'draft'`) et `data()` le cas. Un objet par défaut (`new Address()`) est ignoré : les champs de la classe s'appliquent.
- Un paramètre sans défaut et non nullable devient `required()`, sauf les tableaux et les classes imbriquées.
- Une classe imbriquée nullable (`?Address $address`, avec ou sans `= null`) est facultative : absente, elle vaut `null`.
- Le deuxième argument remplace ou complète les schémas générés, par exemple pour ajouter des contrôles ou typer les éléments d'un tableau.

## Résultat

```php
$result = $schema->validate($data);
```

- `isValid(): bool`
- `values(): mixed` Les valeurs nettoyées, avant `transform()` et `castTo()`. Toujours disponible, même si les données sont invalides. Toutes les clés déclarées d'une structure y figurent.
- `errors(): array` Les codes d'erreur, rangés comme les données. Vide si les données sont valides.
- `error(): string|array|null` L'erreur telle quelle : le code de l'élément, les erreurs de ses enfants, ou `null`. Utile pour valider un élément seul : `Schema::email()->validate($v)->error()` donne `'email'` plutôt que `['' => 'email']`.
- `data(): mixed` Les valeurs après `transform()` et `castTo()`. Lève `LogicException` si les données sont invalides.

Quand le nettoyage d'un champ échoue (code `type`, ou `Schema::fail()` dans un `before()`), `values()` contient sa valeur par défaut plutôt que la saisie. Un tableau y vaut toujours `[]`, et une structure les valeurs par défaut de ses champs.

## Erreurs

Les erreurs suivent la forme des données. Une feuille est un code, une structure ou un tableau est un tableau de codes :

```php
[
    'name'    => 'required',
    'address' => ['zip' => 'pattern'],
    'lines'   => [3 => ['qty' => 'min']],
    'tags'    => [1 => 'max'],
]
```

Un élément porte soit son propre code, soit les erreurs de ses enfants, jamais les deux. L'erreur de l'élément racine lui-même (par exemple `type` quand les données ne sont pas un tableau) est sous la clé `''`.

| Code | Origine |
| ---- | ------- |
| `type` | le nettoyage n'a pas pu convertir la valeur, ou `castTo()` vers un enum a reçu une valeur inconnue |
| `required` | valeur absente, vide, ou tableau vide |
| `min`, `max` | longueur d'une chaîne, valeur d'un nombre, nombre d'éléments d'un tableau |
| `length`, `alpha`, `alnum`, `digits`, `pattern` | `length()`, `alpha()`... sur une chaîne |
| `multipleOf` | `multipleOf()` sur un nombre |
| `unique` | `unique()` sur un tableau |
| `same`, `different` | `same()`, `different()` sur une structure |
| `date`, `datetime`, `time`, `email`, `url`, `uuid`, `ip`, `json` | format de `Schema::date()`... |
| `anyOf` | aucune valeur de `anyOf()` ou `enum()` ne correspond (avec des schémas : les erreurs du plus proche) |
| `list` | `listOf()` avec des clés non séquentielles |
| `key` | clé refusée par le schéma de clé d'`arrayOf()` |
| `assert` | `assert()` sans code |
| tes codes | `assert(..., 'code')`, `Schema::fail('code')` |

## Messages

`Bredala\Validation\Messages` traduit les codes en messages. Sa configuration suit la forme des erreurs :

- `create(): static` Un nouvel objet, pour chaîner.
- `field(string $name, array $messages): static` Associe des codes à des messages pour une clé. Redéclarer une clé remplace toute sa table.
- `nested(string $name, Messages $messages): static` Les messages des erreurs situées sous cette clé.
- `'*'` Désigne n'importe quelle clé, pour `field()` comme pour `nested()` : typiquement, les éléments d'un tableau.
- `parse(array $errors): array` Transforme `Result::errors()` en messages, en conservant leur forme.

Pour chaque code, la recherche se fait dans cet ordre : le code dans la table de la clé, l'entrée `'default'` de la clé, le code dans la table `'*'`, l'entrée `'default'` de `'*'`, et enfin le code brut. Rien n'est donc jamais perdu, mais un code non configuré s'affiche tel quel.

```php
$messages = Messages::create()
    ->field('*', ['required' => 'Ce champ est obligatoire'])
    ->field('email', ['email' => 'Email invalide'])
    ->nested('address', Messages::create()
        ->field('zip', ['pattern' => 'Code postal invalide']))
    ->nested('lines', Messages::create()->nested('*', Messages::create()
        ->field('qty', ['min' => 'Au moins 1'])))
    ->nested('tags', Messages::create()
        ->field('*', ['max' => 'Tag trop long']));

$messages->parse([
    'name'    => 'required',
    'address' => ['zip' => 'pattern'],
    'lines'   => [3 => ['qty' => 'min']],
    'tags'    => [1 => 'max'],
]);
// [
//     'name'    => 'Ce champ est obligatoire',
//     'address' => ['zip' => 'Code postal invalide'],
//     'lines'   => [3 => ['qty' => 'Au moins 1']],
//     'tags'    => [1 => 'Tag trop long'],
// ]
```

Un `Messages` enfant n'hérite pas de la table `'*'` de son parent.

## Filtres et règles

Les filtres (`Bredala\Validation\Filters`) prennent une valeur et en rendent une autre ; les types s'en servent pour nettoyer l'entrée. Ils restent publics, pour s'en servir dans un `before()` ou un `transform()`. Ils lèvent une `ValidationException` (code `type`) quand la valeur est inconvertible.

### StringFilter

- `input(mixed $value): ?string` Chaîne sur une ligne : toute espace blanche devient une espace.
- `text(mixed $value): ?string` Chaîne multiligne : conserve au maximum deux retours à la ligne consécutifs et rogne chaque ligne.
- `raw(mixed $value): ?string` Conversion en chaîne sans nettoyage : `''` devient `null`, les nombres et les objets `Stringable` sont convertis, le reste lève `type`.
- `sanitize(mixed $value, bool $multiline = false): ?string` `input()` ou `text()`, explicitement.
- `stripTags(string $string): string` Retire les balises en protégeant `<` suivi d'un chiffre.
- `sanitizeUrl()`, `sanitizeEmail()`, `sanitizeType(mixed $value, int $type)` Via `filter_var` et un filtre `FILTER_SANITIZE_*`.

La chaîne de traitement de `input()` et `text()`, pour une chaîne : réparation UTF-8 (`htmlentities` puis `html_entity_decode`) → retrait des balises → suppression des caractères non imprimables et de `U+FFFD` → normalisation des espaces exotiques → fusion des espaces consécutives → rognage → `''` devient `null`.

Quelques conséquences :

- Une saisie composée uniquement d'espaces devient `null`.
- Les entiers et les flottants sont convertis directement : `42` devient `'42'`.
- Une valeur qui n'est ni une chaîne ni un nombre lève `type` : tableaux, objets, et notamment `true`.
- Les balises sont retirées mais **leur contenu textuel survit** : `'<script>alert(1)</script>'` devient `'alert(1)'`. C'est un nettoyage, pas une protection HTML : il faut toujours échapper à l'affichage.
- Les entités sont **décodées** : `'a&amp;b'` devient `'a&b'`.
- `'a <3 b'` est préservé.
- `sanitizeUrl()`, `sanitizeEmail()` et `sanitizeType()` retournent `null` au lieu de lever `type`, et ne font que nettoyer : `sanitizeEmail('a(x)@b.test')` retourne `'ax@b.test'`. Pour valider, utiliser `Schema::email()` et `Schema::url()`.

### IntegerFilter, DecimalFilter, BooleanFilter

- `IntegerFilter::sanitize(mixed $value): ?int` `null` et `''` (après rognage) deviennent `null` ; les numériques sont convertis par `(int)`, donc **les flottants sont tronqués** (`'1.9'` donne `1`) ; le reste lève `type`. La notation exponentielle est acceptée (`'1e3'`), pas l'hexadécimal (`'0x1A'`) ni les unités (`'10px'`).
- `DecimalFilter::sanitize(mixed $value): ?float` Pareil, converti par `(float)` : `3` donne `3.0`.
- `BooleanFilter::sanitize(mixed $value): ?bool` Les booléens passent ; les numériques sont convertis par `(int)`, puis `0`/`1` donnent faux/vrai (`'1.9'` donne donc vrai) ; les autres chaînes passent par `FILTER_VALIDATE_BOOLEAN` (`yes`/`no`, `on`/`off`, `true`/`false`) ; le reste lève `type`.

### ArrayFilter

- `sanitize(mixed $value): ?array` Retourne le tableau, ou `null`. Un tableau vide devient `null`, comme `''`. Un scalaire lève `type`, y compris `0` et `false`.
- `map(mixed $value, callable $callback): ?array` et `mapInput()`, `mapText()`, `mapInteger()`, `mapDecimal()`, `mapBoolean()`, `mapArray()` Appliquent une fonction à chaque élément, en conservant les clés.

### Règles

Les règles (`Bredala\Validation\Rules`) prennent une valeur et rendent un booléen. Les contrôles intégrés s'en servent, et on peut les utiliser dans un `assert()` :

```php
use Bredala\Validation\Rules\StringRule;

Schema::mixed()->assert(fn($v) => is_string($v) && StringRule::maxLength($v, 10), 'max');
```

`StringRule` :

- `minLength(string $value, int $min): bool`, `maxLength(string $value, int $max): bool`, `length(string $value, int $length): bool` En caractères.
- `isAlpha(string $value): bool`, `isAlnum(string $value): bool` Lettres Unicode, et chiffres pour `isAlnum()`.
- `isDigits(string $value): bool` Chiffres ASCII seulement.
- `matches(string $value, string $pattern): bool` Toute la chaîne, expression sans délimiteurs, Unicode.
- `isDate(string $value, string ...$formats): bool` Le contrôle strict des dates.
- `isEmail(string $value): bool` `FILTER_VALIDATE_EMAIL`.
- `isUrl(string $value): bool` Une URL `http` ou `https`.
- `isUuid(string $value): bool` Forme canonique, toute version.
- `isIp(string $value, ?int $version = null): bool` IPv4 ou IPv6, ou seulement `4` ou `6`.
- `isJson(string $value): bool` `json_validate()`.

`NumberRule` :

- `min(int|float $value, int|float $min): bool`, `max(int|float $value, int|float $max): bool` Bornes incluses.
- `isMultipleOf(int|float $value, int|float $step): bool` Tolère l'arrondi des flottants ; un pas de `0` refuse tout.

`ArrayRule` :

- `minCount(array $value, int $min): bool`, `maxCount(array $value, int $max): bool`
- `isList(array $value): bool` Clés `0, 1, 2...`
- `isUnique(array $value): bool` Aucun doublon, comparaison stricte (`1` et `'1'` diffèrent).

### Rejet

- `Schema::fail(string $code): never` Rejette la valeur avec un code, depuis un `before()`, un `assert()` ou un `transform()`. Lève une `Bredala\Validation\ValidationException` dont le message est le code.

## Migration depuis la v5

La classe `Form` et les règles statiques (`StringField::min()`, `Field::required()`, `Field::skip()`...) n'existent plus.

| v5 | v6 |
| -- | -- |
| `class UserForm extends Form` | `Schema::structure([...])`, ou `Schema::from(User::class)` |
| `$this->input('name', $rule)` | `'name' => Schema::input()` |
| `Field::required($v)` | `->required()` |
| `Field::skip($v)` | rien : les éléments sont facultatifs par défaut |
| `StringField::max($v, 50)`, `IntegerField::range($v, 18, 99)` | `->max(50)`, `->min(18)->max(99)` |
| `Field::include($v, [...])` | `Schema::anyOf(...)` ou `Schema::enum()` |
| une règle qui lève un code | `->assert(fn($v) => ..., 'code')` |
| `Field::trigger('code')` | `Schema::fail('code')` |
| `FieldException`, `SkipException` | `ValidationException` ; plus de `SkipException` |
| `Fields\StringField::input()`... | `Filters\StringFilter::input()`... |
| `Field::match($v, $x)`, `Field::differ($v, $x)` | entre deux champs : `->same()`, `->different()` sur la structure ; avec une valeur fixe : `->assert(fn($v) => $v === $x, 'match')` |
| `Field::exclude($v, [...])` | `->assert(fn($v) => !in_array($v, [...], true), 'exclude')` |
| `$this->setValue()` dans une règle | `->before()` pour `values()`, `->transform()` pour `data()` |
| `$form->validate($data)` | `$result = $schema->validate($data)` |
| `$form->values()`, `errors()` | `$result->values()`, `errors()` |
| `$form->value('name')`, `error('name')` | `$result->values()['name']`, `$result->errors()['name'] ?? null` |
| `$form->setError()` | un `assert()` de structure avec son champ |
| code `range` | `min` ou `max` |

## Tests

```bash
composer install
vendor/bin/phpunit
```
