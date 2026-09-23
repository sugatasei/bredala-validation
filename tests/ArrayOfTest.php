<?php

use Bredala\Validation\Result;
use Bredala\Validation\Schema;
use PHPUnit\Framework\TestCase;

final class ArrayOfTestLine
{
    public function __construct(
        public readonly string $sku,
        public readonly int $qty,
    ) {
    }
}

class ArrayOfTest extends TestCase
{
    private static function process(Schema $schema, mixed $value): Result
    {
        return $schema->validate($value);
    }

    private static function line(): Schema
    {
        return Schema::structure([
            'sku' => Schema::input()->required(),
            'qty' => Schema::int()->required()->min(1),
        ]);
    }

    public function testEachElementIsProcessedByTheItemSchema()
    {
        $result = self::process(Schema::arrayOf(Schema::int()), ['1', ' 2 ']);

        self::assertSame([1, 2], $result->values());
        self::assertSame([1, 2], $result->data());
    }

    public function testKeysArePreserved()
    {
        $result = self::process(Schema::arrayOf(self::line()), [3 => ['sku' => 'A', 'qty' => '1'], 'x' => ['sku' => 'B', 'qty' => '2']]);

        self::assertSame([3, 'x'], array_keys($result->values()));
    }

    public function testErrorsAreIndexedByKeyAndOnlyListInvalidElements()
    {
        $result = self::process(Schema::arrayOf(Schema::input()->max(3)), ['abc', 'abcd', 'ab', 'abcde']);

        self::assertSame([1 => 'max', 3 => 'max'], $result->errors());
    }

    public function testNestedStructureErrors()
    {
        $result = self::process(Schema::arrayOf(self::line()), [['sku' => 'A', 'qty' => 1], ['sku' => '', 'qty' => 0]]);

        self::assertSame([1 => ['sku' => 'required', 'qty' => 'min']], $result->errors());
        self::assertSame([['sku' => 'A', 'qty' => 1], ['sku' => null, 'qty' => 0]], $result->values());
    }

    public function testAScalarElementOfAStructureListIsATypeErrorForThatElement()
    {
        $result = self::process(Schema::arrayOf(self::line()), [['sku' => 'A', 'qty' => 1], 'oops']);

        self::assertSame([1 => 'type'], $result->errors());
    }

    public function testArraysNestAtAnyDepth()
    {
        $schema = Schema::arrayOf(Schema::structure([
            'lines' => Schema::arrayOf(self::line()),
        ]));

        $result = self::process($schema, [
            ['lines' => [['sku' => 'A', 'qty' => 2]]],
            ['lines' => [['sku' => 'B', 'qty' => 0]]],
        ]);

        self::assertSame([1 => ['lines' => [0 => ['qty' => 'min']]]], $result->errors());
    }

    // -------------------------------------------------------------------------
    // Absent / empty
    // -------------------------------------------------------------------------

    /**
     * @dataProvider absentProvider
     */
    public function testAnAbsentArrayDefaultsToAnEmptyArray(mixed $input)
    {
        $result = self::process(Schema::arrayOf(Schema::int()), $input);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->values());
        self::assertSame([], $result->data());
    }

    public static function absentProvider(): array
    {
        return ['null' => [null], 'empty string' => [''], 'empty array' => [[]]];
    }

    public function testARequiredArrayRejectsAnEmptyArray()
    {
        self::assertSame(['' => 'required'], self::process(Schema::arrayOf(Schema::int())->required(), [])->errors());
    }

    public function testMinAppliesToAnAbsentArray()
    {
        self::assertSame(['' => 'min'], self::process(Schema::arrayOf(Schema::int())->min(1), null)->errors());
    }

    public function testAScalarInputIsATypeError()
    {
        $result = self::process(Schema::arrayOf(Schema::int()), 'oops');

        self::assertSame(['' => 'type'], $result->errors());
        self::assertSame([], $result->values());
    }

    // -------------------------------------------------------------------------
    // min / max / listOf / keys
    // -------------------------------------------------------------------------

    public function testMinAndMaxCountElements()
    {
        $schema = Schema::arrayOf(Schema::int())->min(2)->max(3);

        self::assertSame(['' => 'min'], self::process($schema, [1])->errors());
        self::assertTrue(self::process($schema, [1, 2])->isValid());
        self::assertSame(['' => 'max'], self::process($schema, [1, 2, 3, 4])->errors());
    }

    public function testTheArrayOwnErrorWinsOverTheElementErrors()
    {
        $result = self::process(Schema::arrayOf(Schema::int()->min(10))->max(1), [1, 2]);

        self::assertSame(['' => 'max'], $result->errors());
        self::assertSame([1, 2], $result->values());
    }

    public function testListOfRequiresSequentialKeys()
    {
        self::assertTrue(self::process(Schema::listOf(Schema::int()), [1, 2])->isValid());
        self::assertSame(['' => 'list'], self::process(Schema::listOf(Schema::int()), [1 => 1, 2 => 2])->errors());
        self::assertSame(['' => 'list'], self::process(Schema::listOf(Schema::int()), ['a' => 1])->errors());
    }

    public function testArrayOfAcceptsAnyKeys()
    {
        self::assertTrue(self::process(Schema::arrayOf(Schema::int()), [5 => 1, 'a' => 2])->isValid());
    }

    public function testAKeySchemaValidatesTheKeys()
    {
        $schema = Schema::arrayOf(Schema::int(), Schema::input()->pattern('[a-z]+'));

        self::assertTrue(self::process($schema, ['a' => 1, 'bc' => 2])->isValid());
        self::assertSame([0 => 'key', 'B' => 'key'], self::process($schema, ['a' => 1, 0 => 2, 'B' => 3])->errors());
    }

    public function testAKeyErrorWinsOverTheElementError()
    {
        $schema = Schema::arrayOf(Schema::int(), Schema::int());

        self::assertSame(['x' => 'key'], self::process($schema, ['x' => 'abc'])->errors());
    }

    // -------------------------------------------------------------------------
    // assert / transform / castTo
    // -------------------------------------------------------------------------

    public function testAnAssertReceivesTheSanitizedElements()
    {
        $schema = Schema::arrayOf(Schema::int())->assert(fn(array $v) => count(array_unique($v)) === count($v), 'unique');

        self::assertTrue(self::process($schema, ['1', '2'])->isValid());
        self::assertSame(['' => 'unique'], self::process($schema, ['1', ' 1'])->errors());
    }

    public function testAnAssertOnlyRunsWhenAllElementsAreValid()
    {
        $schema = Schema::arrayOf(Schema::int())->assert(fn() => false, 'never');

        self::assertSame([0 => 'type'], self::process($schema, ['abc'])->errors());
    }

    public function testElementsAreCastBeforeTheArray()
    {
        $schema = Schema::arrayOf(self::line()->castTo(ArrayOfTestLine::class))->castTo(ArrayObject::class);

        $data = self::process($schema, [['sku' => 'A', 'qty' => '2']])->data();

        self::assertInstanceOf(ArrayObject::class, $data);
        self::assertInstanceOf(ArrayOfTestLine::class, $data[0]);
    }

    public function testTransformReceivesTheElementsData()
    {
        $schema = Schema::arrayOf(Schema::int()->castTo('string'))->transform(fn(array $v) => implode(',', $v));

        self::assertSame('1,2', self::process($schema, ['1', '2'])->data());
    }
}
