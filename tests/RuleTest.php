<?php

use Bredala\Validation\Rules\ArrayRule;
use Bredala\Validation\Rules\NumberRule;
use Bredala\Validation\Rules\StringRule;
use Bredala\Validation\Schema;
use PHPUnit\Framework\TestCase;

class RuleTest extends TestCase
{
    // -------------------------------------------------------------------------
    // StringRule
    // -------------------------------------------------------------------------

    public function testLengthCountsCharactersNotBytes()
    {
        self::assertTrue(StringRule::minLength('été', 3));
        self::assertFalse(StringRule::minLength('été', 4));
        self::assertTrue(StringRule::maxLength('été', 3));
        self::assertFalse(StringRule::maxLength('été', 2));
    }

    public function testMatchesTheWholeString()
    {
        self::assertTrue(StringRule::matches('75001', '\d{5}'));
        self::assertFalse(StringRule::matches('750011', '\d{5}'));
        self::assertFalse(StringRule::matches("75001\n", '\d{5}'));
        self::assertTrue(StringRule::matches('a|b', 'a\|b'));
        self::assertTrue(StringRule::matches('a~b', 'a~b'));
        self::assertTrue(StringRule::matches('é', '\w'));
    }

    public function testIsEmail()
    {
        self::assertTrue(StringRule::isEmail('tom@example.com'));
        self::assertFalse(StringRule::isEmail('tom@'));
    }

    public function testIsUrlAcceptsOnlyHttpAndHttps()
    {
        self::assertTrue(StringRule::isUrl('https://example.com'));
        self::assertTrue(StringRule::isUrl('HTTP://example.com'));
        self::assertFalse(StringRule::isUrl('ftp://example.com'));
        self::assertFalse(StringRule::isUrl('javascript:alert(1)'));
    }

    // -------------------------------------------------------------------------
    // NumberRule
    // -------------------------------------------------------------------------

    public function testBoundsAreInclusive()
    {
        self::assertTrue(NumberRule::min(18, 18));
        self::assertFalse(NumberRule::min(17.9, 18));
        self::assertTrue(NumberRule::max(1.5, 1.5));
        self::assertFalse(NumberRule::max(2, 1.5));
    }

    // -------------------------------------------------------------------------
    // ArrayRule
    // -------------------------------------------------------------------------

    public function testCountAndList()
    {
        self::assertTrue(ArrayRule::minCount([1, 2], 2));
        self::assertFalse(ArrayRule::minCount([1], 2));
        self::assertTrue(ArrayRule::maxCount([], 0));
        self::assertFalse(ArrayRule::maxCount([1], 0));
        self::assertTrue(ArrayRule::isList([1, 2]));
        self::assertFalse(ArrayRule::isList([1 => 1]));
    }

    // -------------------------------------------------------------------------
    // Shortcuts
    // -------------------------------------------------------------------------

    public function testDeclaringARuleAgainReplacesIt()
    {
        self::assertTrue(Schema::input()->max(2)->max(5)->validate('abcd')->isValid());
        self::assertTrue(Schema::int()->min(10)->min(1)->validate(5)->isValid());
        self::assertTrue(Schema::arrayOf(Schema::int())->max(1)->max(3)->validate([1, 2])->isValid());
    }

    public function testRulesRunInDeclarationOrderBeforeAsserts()
    {
        $schema = Schema::date()->max(4)->assert(fn() => false, 'assert');

        self::assertSame('date', Schema::date()->max(4)->validate('2026-13-01')->error());
        self::assertSame('max', $schema->validate('2026-10-01')->error());
    }

    public function testBoolAndMixedHaveNoLengthOrBounds()
    {
        self::assertFalse(method_exists(Schema::bool(), 'min'));
        self::assertFalse(method_exists(Schema::mixed(), 'pattern'));
    }

    public function testAnAbsentArrayIsCheckedAgainstItsDefault()
    {
        self::assertSame('min', Schema::arrayOf(Schema::int())->min(1)->validate(null)->error());
        self::assertTrue(Schema::arrayOf(Schema::int())->default([1])->min(1)->validate(null)->isValid());
    }

    // -------------------------------------------------------------------------
    // Classic rules
    // -------------------------------------------------------------------------

    /**
     * @dataProvider stringRuleProvider
     */
    public function testStringRules(Schema $schema, string $value, ?string $error)
    {
        self::assertSame($error, $schema->validate($value)->error());
    }

    public static function stringRuleProvider(): array
    {
        return [
            'length ok' => [Schema::input()->length(5), 'été75', null],
            'length ko' => [Schema::input()->length(5), '7500', 'length'],
            'alpha accents' => [Schema::input()->alpha(), 'Élodie', null],
            'alpha space' => [Schema::input()->alpha(), 'Jean Paul', 'alpha'],
            'alpha digit' => [Schema::input()->alpha(), 'abc1', 'alpha'],
            'alnum' => [Schema::input()->alnum(), 'abc123é', null],
            'alnum dash' => [Schema::input()->alnum(), 'abc-1', 'alnum'],
            'digits leading zero' => [Schema::input()->digits(), '0612', null],
            'digits negative' => [Schema::input()->digits(), '-1', 'digits'],
            'digits decimal' => [Schema::input()->digits(), '1.5', 'digits'],
            'uuid' => [Schema::uuid(), '550E8400-e29b-41d4-a716-446655440000', null],
            'uuid braces' => [Schema::uuid(), '{550e8400-e29b-41d4-a716-446655440000}', 'uuid'],
            'uuid short' => [Schema::uuid(), '550e8400-e29b-41d4-a716', 'uuid'],
            'ipv4' => [Schema::ip(), '192.168.0.1', null],
            'ipv6' => [Schema::ip(), '::1', null],
            'ip garbage' => [Schema::ip(), '256.1.1.1', 'ip'],
            'ip v4 only' => [Schema::ip(4), '::1', 'ip'],
            'ip v6 only' => [Schema::ip(6), '10.0.0.1', 'ip'],
            'json' => [Schema::json(), '{"a": "<b>"}', null],
            'json ko' => [Schema::json(), '{a: 1}', 'json'],
        ];
    }

    public function testJsonIsNotCleaned()
    {
        self::assertSame('{"a": "<b>x</b>"}', Schema::json()->validate('{"a": "<b>x</b>"}')->values());
    }

    /**
     * @dataProvider multipleOfProvider
     */
    public function testMultipleOf(int|float $value, int|float $step, bool $expected)
    {
        self::assertSame($expected, NumberRule::isMultipleOf($value, $step));
    }

    public static function multipleOfProvider(): array
    {
        return [
            'int' => [15, 5, true],
            'int ko' => [16, 5, false],
            'zero' => [0, 5, true],
            'negative' => [-10, 5, true],
            'float rounding' => [0.3, 0.1, true],
            'price' => [19.99, 0.01, true],
            'float ko' => [0.35, 0.1, false],
            'step zero' => [5, 0, false],
        ];
    }

    public function testMultipleOfShortcut()
    {
        self::assertSame('multipleOf', Schema::int()->multipleOf(5)->validate('12')->error());
        self::assertTrue(Schema::float()->multipleOf(0.5)->validate('2.5')->isValid());
    }

    public function testUniqueComparesSanitizedValues()
    {
        $schema = Schema::arrayOf(Schema::int())->unique();

        self::assertSame('unique', $schema->validate(['1', 1])->error());
        self::assertTrue($schema->validate([1, 2])->isValid());
        self::assertFalse(ArrayRule::isUnique([[1], [1]]));
        self::assertTrue(ArrayRule::isUnique([1, '1']));
    }

    public function testUniqueOnlyRunsWhenAllElementsAreValid()
    {
        $schema = Schema::arrayOf(Schema::int()->max(5))->unique();

        self::assertSame([1 => 'max', 2 => 'max'], $schema->validate([1, 9, 9])->error());
    }

    public function testSameAndDifferent()
    {
        $schema = Schema::structure([
            'password' => Schema::string()->required(),
            'confirm' => Schema::string()->required(),
            'old' => Schema::string(),
        ])->same('confirm', 'password')->different('password', 'old');

        self::assertSame(['confirm' => 'same'], $schema->validate(['password' => 'a', 'confirm' => 'b'])->errors());
        self::assertSame(['password' => 'different'], $schema->validate(['password' => 'a', 'confirm' => 'a', 'old' => 'a'])->errors());
        self::assertTrue($schema->validate(['password' => 'a', 'confirm' => 'a', 'old' => 'b'])->isValid());
    }
}
