<?php

use Bredala\Validation\Result;
use Bredala\Validation\Schema;
use PHPUnit\Framework\TestCase;

enum AnyOfTestStatus: string
{
    case Draft = 'draft';
    case Paid = 'paid';
}

enum AnyOfTestPriority: int
{
    case Low = 1;
    case High = 2;
}

class AnyOfTest extends TestCase
{
    private static function process(Schema $schema, mixed $value): Result
    {
        return $schema->validate($value);
    }

    // -------------------------------------------------------------------------
    // Literals
    // -------------------------------------------------------------------------

    public function testALiteralMatches()
    {
        self::assertSame('paid', self::process(Schema::anyOf('draft', 'paid'), 'paid')->values());
    }

    public function testAStringFromAFormMatchesAnIntLiteralAndBecomesThatInt()
    {
        self::assertSame(2, self::process(Schema::anyOf(1, 2, 3), '2')->values());
    }

    public function testSurroundingSpacesAreIgnored()
    {
        self::assertSame('paid', self::process(Schema::anyOf('draft', 'paid'), ' paid ')->values());
    }

    public function testMatchingIsCaseSensitive()
    {
        self::assertSame(['' => 'anyOf'], self::process(Schema::anyOf('paid'), 'PAID')->errors());
    }

    public function testNoMatchIsAnAnyOfError()
    {
        $result = self::process(Schema::anyOf('draft', 'paid'), 'nope');

        self::assertSame(['' => 'anyOf'], $result->errors());
        self::assertSame('nope', $result->values());
    }

    public function testBoolLiteralsOnlyMatchStrictly()
    {
        self::assertTrue(self::process(Schema::anyOf(true), true)->isValid());
        self::assertFalse(self::process(Schema::anyOf(true), '1')->isValid());
    }

    public function testAnEmptyInputIsMissing()
    {
        self::assertTrue(self::process(Schema::anyOf('a'), '')->isValid());
        self::assertSame(['' => 'required'], self::process(Schema::anyOf('a')->required(), '')->errors());
    }

    public function testAnArrayNeverMatchesALiteral()
    {
        $result = self::process(Schema::anyOf('a'), ['a']);

        self::assertSame(['' => 'anyOf'], $result->errors());
        self::assertNull($result->values());
    }

    // -------------------------------------------------------------------------
    // Schemas
    // -------------------------------------------------------------------------

    public function testTheFirstValidSchemaWins()
    {
        $schema = Schema::anyOf(Schema::int(), Schema::input());

        self::assertSame(42, self::process($schema, '42')->values());
        self::assertSame('abc', self::process($schema, 'abc')->values());
    }

    public function testLiteralsAndSchemasMix()
    {
        $schema = Schema::anyOf('auto', Schema::int()->min(1));

        self::assertSame('auto', self::process($schema, 'auto')->values());
        self::assertSame(5, self::process($schema, '5')->values());
    }

    private static function payment(): Schema
    {
        return Schema::anyOf(
            Schema::structure([
                'type' => Schema::anyOf('card')->required(),
                'number' => Schema::input()->required(),
            ]),
            Schema::structure([
                'type' => Schema::anyOf('paypal')->required(),
                'email' => Schema::email()->required(),
            ]),
        );
    }

    public function testAStructureIsPickedByItsDiscriminant()
    {
        $result = self::process(self::payment(), ['type' => 'paypal', 'email' => 'tom@example.com']);

        self::assertSame(['type' => 'paypal', 'email' => 'tom@example.com'], $result->data());
    }

    public function testWhenNoSchemaMatchesTheErrorsOfTheClosestOneAreReported()
    {
        $result = self::process(self::payment(), ['type' => 'paypal', 'email' => 'bad']);

        self::assertSame(['email' => 'email'], $result->errors());
        self::assertSame(['type' => 'paypal', 'email' => 'bad'], $result->values());
    }

    public function testOnATieTheFirstSchemaIsReported()
    {
        $result = self::process(self::payment(), ['type' => 'bitcoin']);

        self::assertSame(['type' => 'anyOf', 'number' => 'required'], $result->errors());
    }

    public function testTheChosenSchemaDataIsKept()
    {
        $schema = Schema::anyOf(Schema::date()->castTo(DateTimeImmutable::class), Schema::input());

        self::assertInstanceOf(DateTimeImmutable::class, self::process($schema, '2026-10-15')->data());
    }

    public function testAssertTransformAndCastToApplyToTheMatch()
    {
        $schema = Schema::anyOf(1, 2)->assert(fn($v) => $v !== 2, 'no_two')->castTo('string');

        self::assertSame('1', self::process($schema, '1')->data());
        self::assertSame(['' => 'no_two'], self::process($schema, '2')->errors());
    }

    // -------------------------------------------------------------------------
    // enum
    // -------------------------------------------------------------------------

    public function testEnumKeepsTheValueInValuesAndTheCaseInData()
    {
        $result = self::process(Schema::enum(AnyOfTestStatus::class), 'paid');

        self::assertSame('paid', $result->values());
        self::assertSame(AnyOfTestStatus::Paid, $result->data());
    }

    public function testAnIntBackedEnumAcceptsAString()
    {
        $result = self::process(Schema::enum(AnyOfTestPriority::class), '2');

        self::assertSame(2, $result->values());
        self::assertSame(AnyOfTestPriority::High, $result->data());
    }

    public function testAnUnknownEnumValueIsAnAnyOfError()
    {
        self::assertSame(['' => 'anyOf'], self::process(Schema::enum(AnyOfTestStatus::class), 'nope')->errors());
    }

    public function testEnumRequiresABackedEnum()
    {
        $this->expectException(InvalidArgumentException::class);

        Schema::enum(stdClass::class);
    }
}
