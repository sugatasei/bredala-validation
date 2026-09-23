<?php

use Bredala\Validation\Result;
use Bredala\Validation\Schema;
use PHPUnit\Framework\TestCase;

enum TypeTestStatus: string
{
    case Draft = 'draft';
    case Paid = 'paid';
}

enum TypeTestPure
{
    case A;
}

final class TypeTestEmail
{
    public function __construct(public readonly string $value)
    {
    }
}

class TypeTest extends TestCase
{
    private static function process(Schema $schema, mixed $value): Result
    {
        return $schema->validate($value);
    }

    // -------------------------------------------------------------------------
    // Built-in sanitizing
    // -------------------------------------------------------------------------

    /**
     * @dataProvider sanitizeProvider
     */
    public function testEachTypeSanitizesItsInput(Schema $schema, mixed $input, mixed $expected)
    {
        $result = self::process($schema, $input);

        self::assertTrue($result->isValid());
        self::assertSame($expected, $result->values());
    }

    public static function sanitizeProvider(): array
    {
        return [
            'input trims and flattens' => [Schema::input(), "  a \n b  ", 'a b'],
            'input strips tags' => [Schema::input(), '<b>x</b>', 'x'],
            'text keeps newlines' => [Schema::text(), "a\nb", "a\nb"],
            'string keeps everything' => [Schema::string(), "  <b>a</b>\n ", "  <b>a</b>\n "],
            'string converts an int' => [Schema::string(), 42, '42'],
            'string converts a Stringable' => [Schema::string(), new class { public function __toString(): string { return 'x'; } }, 'x'],
            'int from a string' => [Schema::int(), ' 42 ', 42],
            'float from a string' => [Schema::float(), '1.5', 1.5],
            'bool from on' => [Schema::bool(), 'on', true],
            'bool from 0' => [Schema::bool(), '0', false],
            'mixed keeps the value' => [Schema::mixed(), ['a' => 1], ['a' => 1]],
            'mixed keeps an empty string' => [Schema::mixed(), '', ''],
        ];
    }

    /**
     * @dataProvider emptyProvider
     */
    public function testEmptyInputBecomesNull(Schema $schema, mixed $input)
    {
        self::assertNull(self::process($schema, $input)->values());
    }

    public static function emptyProvider(): array
    {
        return [
            'input blank' => [Schema::input(), '   '],
            'string empty' => [Schema::string(), ''],
            'int empty' => [Schema::int(), ''],
            'bool empty' => [Schema::bool(), ''],
            'null' => [Schema::input(), null],
        ];
    }

    public function testStringKeepsWhitespaceOnlyInput()
    {
        self::assertSame('   ', self::process(Schema::string(), '   ')->values());
    }

    /**
     * @dataProvider typeErrorProvider
     */
    public function testAnInputTheTypeCannotConvertIsATypeError(Schema $schema, mixed $input)
    {
        $result = self::process($schema, $input);

        self::assertSame(['' => 'type'], $result->errors());
        self::assertNull($result->values());
    }

    public static function typeErrorProvider(): array
    {
        return [
            'int from a word' => [Schema::int(), 'abc'],
            'float from a word' => [Schema::float(), 'abc'],
            'bool from a word' => [Schema::bool(), 'maybe'],
            'input from an array' => [Schema::input(), ['a']],
            'string from an array' => [Schema::string(), ['a']],
            'string from a bool' => [Schema::string(), true],
        ];
    }

    public function testATypeErrorKeepsTheDefaultInValues()
    {
        self::assertSame(18, self::process(Schema::int()->default(18), 'abc')->values());
    }

    // -------------------------------------------------------------------------
    // required / default
    // -------------------------------------------------------------------------

    public function testAnOptionalFieldAcceptsNull()
    {
        $result = self::process(Schema::input(), null);

        self::assertTrue($result->isValid());
        self::assertNull($result->data());
    }

    public function testARequiredFieldRejectsNullAndEmptyInput()
    {
        self::assertSame(['' => 'required'], self::process(Schema::input()->required(), null)->errors());
        self::assertSame(['' => 'required'], self::process(Schema::input()->required(), '  ')->errors());
        self::assertSame(['' => 'required'], self::process(Schema::string()->required(), '')->errors());
    }

    public function testRequiredCanBeTurnedOff()
    {
        self::assertTrue(self::process(Schema::input()->required()->required(false), null)->isValid());
    }

    public function testTheDefaultReplacesAMissingValue()
    {
        $result = self::process(Schema::int()->default(18), null);

        self::assertSame(18, $result->values());
        self::assertSame(18, $result->data());
    }

    public function testChecksAreSkippedForAMissingValue()
    {
        self::assertTrue(self::process(Schema::input()->min(3)->pattern('\d+'), null)->isValid());
    }

    // -------------------------------------------------------------------------
    // min / max / pattern
    // -------------------------------------------------------------------------

    public function testMinAndMaxCountCharactersForStrings()
    {
        self::assertTrue(self::process(Schema::input()->min(3)->max(3), 'éàü')->isValid());
        self::assertSame(['' => 'min'], self::process(Schema::input()->min(4), 'éàü')->errors());
        self::assertSame(['' => 'max'], self::process(Schema::input()->max(2), 'éàü')->errors());
    }

    public function testMinAndMaxCompareValuesForNumbers()
    {
        self::assertTrue(self::process(Schema::int()->min(18)->max(99), '18')->isValid());
        self::assertSame(['' => 'min'], self::process(Schema::int()->min(18), '17')->errors());
        self::assertSame(['' => 'max'], self::process(Schema::float()->max(1.5), '1.6')->errors());
    }

    public function testPatternMustMatchTheWholeString()
    {
        self::assertTrue(self::process(Schema::input()->pattern('\d{5}'), '75001')->isValid());
        self::assertSame(['' => 'pattern'], self::process(Schema::input()->pattern('\d{5}'), '750011')->errors());
        self::assertSame(['' => 'pattern'], self::process(Schema::input()->pattern('\d{5}'), 'a75001')->errors());
    }

    public function testPatternAcceptsTheDelimiterCharacter()
    {
        self::assertTrue(self::process(Schema::input()->pattern('a~b'), 'a~b')->isValid());
    }

    public function testPatternIsUnicodeAware()
    {
        self::assertTrue(self::process(Schema::input()->pattern('.{3}'), 'éàü')->isValid());
    }

    // -------------------------------------------------------------------------
    // email / url
    // -------------------------------------------------------------------------

    public function testEmail()
    {
        self::assertSame('tom@example.com', self::process(Schema::email(), ' tom@example.com ')->values());
        self::assertSame(['' => 'email'], self::process(Schema::email(), 'tom@')->errors());
    }

    /**
     * @dataProvider urlProvider
     */
    public function testUrlOnlyAcceptsHttpAndHttps(string $url, bool $valid)
    {
        self::assertSame($valid, self::process(Schema::url(), $url)->isValid());
    }

    public static function urlProvider(): array
    {
        return [
            'https' => ['https://example.com/a?b=1', true],
            'http' => ['http://example.com', true],
            'upper case scheme' => ['HTTPS://example.com', true],
            'javascript' => ['javascript://example.com/%0Aalert(1)', false],
            'ftp' => ['ftp://example.com', false],
            'no scheme' => ['example.com', false],
        ];
    }

    // -------------------------------------------------------------------------
    // before
    // -------------------------------------------------------------------------

    public function testBeforeRunsOnTheRawInputBeforeTheSanitizer()
    {
        $schema = Schema::int()->before(fn($v) => str_replace(' ', '', (string) $v));

        self::assertSame(1000000, self::process($schema, '1 000 000')->values());
    }

    public function testBeforeCallbacksRunInOrder()
    {
        $schema = Schema::string()->before(fn($v) => $v . 'a')->before(fn($v) => $v . 'b');

        self::assertSame('xab', self::process($schema, 'x')->values());
    }

    public function testBeforeCanReturnNull()
    {
        $schema = Schema::input()->required()->before(fn($v) => $v === 'n/a' ? null : $v);

        self::assertSame(['' => 'required'], self::process($schema, 'n/a')->errors());
    }

    public function testBeforeCanRejectWithACode()
    {
        $schema = Schema::mixed()->before(fn($v) => is_array($v) ? Schema::fail('scalar') : $v);

        self::assertSame(['' => 'scalar'], self::process($schema, [1])->errors());
    }

    // -------------------------------------------------------------------------
    // assert
    // -------------------------------------------------------------------------

    public function testAssertReceivesTheSanitizedValue()
    {
        $seen = null;
        $schema = Schema::int()->assert(function (int $v) use (&$seen) {
            $seen = $v;
            return true;
        });

        self::process($schema, ' 42 ');

        self::assertSame(42, $seen);
    }

    public function testAFalsyAssertSetsItsCode()
    {
        $schema = Schema::input()->assert(fn(string $v) => $v !== 'admin', 'reserved');

        self::assertTrue(self::process($schema, 'tom')->isValid());
        self::assertSame(['' => 'reserved'], self::process($schema, 'admin')->errors());
    }

    public function testTheDefaultAssertCodeIsAssert()
    {
        self::assertSame(['' => 'assert'], self::process(Schema::input()->assert(fn() => false), 'x')->errors());
    }

    public function testATruthyNonBoolAssertPasses()
    {
        self::assertTrue(self::process(Schema::input()->assert(fn($v) => preg_match('/a/', $v)), 'a')->isValid());
    }

    public function testAnAssertCanTriggerItsOwnCodes()
    {
        $schema = Schema::input()->assert(function (string $v) {
            if (mb_strlen($v) < 12) {
                Schema::fail('too_short');
            }
            if (!preg_match('/\d/', $v)) {
                Schema::fail('no_digit');
            }
            return true;
        });

        self::assertSame(['' => 'too_short'], self::process($schema, 'abc')->errors());
        self::assertSame(['' => 'no_digit'], self::process($schema, 'abcdefghijklm')->errors());
        self::assertTrue(self::process($schema, 'abcdefghijkl1')->isValid());
    }

    public function testAssertsRunAfterTheBuiltInChecksAndInOrder()
    {
        $schema = Schema::input()->max(3)->assert(fn() => false, 'first')->assert(fn() => false, 'second');

        self::assertSame(['' => 'max'], self::process($schema, 'abcd')->errors());
        self::assertSame(['' => 'first'], self::process($schema, 'abc')->errors());
    }

    public function testAssertsAreSkippedForAMissingValue()
    {
        self::assertTrue(self::process(Schema::input()->assert(fn() => false), null)->isValid());
    }

    // -------------------------------------------------------------------------
    // transform / castTo
    // -------------------------------------------------------------------------

    public function testTransformOnlyChangesTheData()
    {
        $result = self::process(Schema::input()->transform('strtoupper'), 'tom');

        self::assertSame('tom', $result->values());
        self::assertSame('TOM', $result->data());
    }

    public function testTransformsRunInOrderAndBeforeCastTo()
    {
        $schema = Schema::input()
            ->transform(fn($v) => $v . '@example.com')
            ->transform('strtolower')
            ->castTo(TypeTestEmail::class);

        $email = self::process($schema, 'TOM')->data();

        self::assertInstanceOf(TypeTestEmail::class, $email);
        self::assertSame('tom@example.com', $email->value);
    }

    public function testATransformCanParseACustomDateFormat()
    {
        $schema = Schema::date('d/m/Y')->transform(
            fn($v) => DateTimeImmutable::createFromFormat('!d/m/Y', $v) ?: Schema::fail('date')
        );

        $result = self::process($schema, '15/10/2026');

        self::assertSame('2026-10-15', $result->data()->format('Y-m-d'));
    }

    public function testATransformErrorMakesTheResultInvalid()
    {
        $result = self::process(Schema::input()->transform(fn() => Schema::fail('unknown')), 'x');

        self::assertSame(['' => 'unknown'], $result->errors());
        self::assertSame('x', $result->values());
    }

    public function testTransformAndCastToSkipNull()
    {
        $schema = Schema::input()->transform(fn() => 'called')->castTo(TypeTestEmail::class);

        self::assertNull(self::process($schema, null)->data());
    }

    public function testTransformAndCastToApplyToADefault()
    {
        self::assertSame('18', self::process(Schema::int()->default(18)->castTo('string'), null)->data());
    }

    public function testTransformsAreSkippedOnAnInvalidValue()
    {
        $called = false;
        $schema = Schema::int()->min(10)->transform(function ($v) use (&$called) {
            $called = true;
            return $v;
        });

        self::process($schema, '5');

        self::assertFalse($called);
    }

    /**
     * @dataProvider scalarCastProvider
     */
    public function testCastToAScalarType(string $type, Schema $schema, mixed $input, mixed $expected)
    {
        self::assertSame($expected, self::process($schema->castTo($type), $input)->data());
    }

    public static function scalarCastProvider(): array
    {
        return [
            'int' => ['int', Schema::input(), '42', 42],
            'float' => ['float', Schema::input(), '1.5', 1.5],
            'string' => ['string', Schema::int(), '42', '42'],
            'bool' => ['bool', Schema::int(), '1', true],
            'array' => ['array', Schema::input(), 'a', ['a']],
        ];
    }

    public function testCastToObject()
    {
        self::assertEquals((object) ['a' => 1], self::process(Schema::mixed()->castTo('object'), ['a' => 1])->data());
    }

    public function testCastToABackedEnum()
    {
        self::assertSame(TypeTestStatus::Paid, self::process(Schema::input()->castTo(TypeTestStatus::class), 'paid')->data());
    }

    public function testCastToABackedEnumWithAnUnknownValueIsATypeError()
    {
        self::assertSame(['' => 'type'], self::process(Schema::input()->castTo(TypeTestStatus::class), 'nope')->errors());
    }

    public function testCastToAClassPassesTheValueToItsConstructor()
    {
        $date = self::process(Schema::date()->castTo(DateTimeImmutable::class), '2026-10-15')->data();

        self::assertSame('2026-10-15', $date->format('Y-m-d'));
    }

    public function testCastToAnUnknownTypeThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        Schema::input()->castTo('integer');
    }

    public function testCastToAPureEnumThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a backed enum');

        Schema::input()->castTo(TypeTestPure::class);
    }

    public function testASchemaIsReusable()
    {
        $schema = Schema::int()->min(1);

        self::assertFalse(self::process($schema, '0')->isValid());
        self::assertTrue(self::process($schema, '1')->isValid());
    }
}
