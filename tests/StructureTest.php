<?php

use Bredala\Validation\Result;
use Bredala\Validation\Schema;
use PHPUnit\Framework\TestCase;

final class StructureTestUser
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {
    }
}

class StructureTestBag
{
    public ?string $name = null;
    public ?int $age = null;
}

class StructureTest extends TestCase
{
    private static function process(Schema $schema, mixed $value): Result
    {
        return $schema->validate($value);
    }

    private static function user(): Schema
    {
        return Schema::structure([
            'name' => Schema::input()->required(),
            'age' => Schema::int()->min(18),
        ]);
    }

    public function testEachItemIsProcessedByItsSchema()
    {
        $result = self::process(self::user(), ['name' => ' Tom ', 'age' => '30']);

        self::assertTrue($result->isValid());
        self::assertSame(['name' => 'Tom', 'age' => 30], $result->values());
        self::assertSame(['name' => 'Tom', 'age' => 30], $result->data());
    }

    public function testUnknownKeysAreDropped()
    {
        $result = self::process(self::user(), ['name' => 'Tom', 'admin' => true]);

        self::assertSame(['name' => 'Tom', 'age' => null], $result->values());
    }

    public function testMissingKeysAreProcessedAsNull()
    {
        self::assertSame(['name' => 'required'], self::process(self::user(), ['age' => 30])->errors());
    }

    public function testErrorsOnlyListTheInvalidItems()
    {
        $result = self::process(self::user(), ['name' => '', 'age' => '12']);

        self::assertSame(['name' => 'required', 'age' => 'min'], $result->errors());
    }

    public function testValuesAreFilledEvenWhenInvalid()
    {
        $result = self::process(self::user(), ['name' => ' Tom ', 'age' => '12']);

        self::assertFalse($result->isValid());
        self::assertSame(['name' => 'Tom', 'age' => 12], $result->values());
    }

    /**
     * @dataProvider absentProvider
     */
    public function testAnAbsentStructureIsProcessedAsAnEmptyArray(mixed $input)
    {
        $result = self::process(self::user(), $input);

        self::assertSame(['name' => 'required'], $result->errors());
        self::assertSame(['name' => null, 'age' => null], $result->values());
    }

    public static function absentProvider(): array
    {
        return ['null' => [null], 'empty string' => [''], 'empty array' => [[]]];
    }

    public function testAScalarInputIsATypeErrorOfTheStructure()
    {
        $result = self::process(self::user(), 'oops');

        self::assertSame(['' => 'type'], $result->errors());
        self::assertSame(['name' => null, 'age' => null], $result->values());
    }

    public function testATypeErrorKeepsTheItemDefaultsInValues()
    {
        $schema = Schema::structure(['age' => Schema::int()->default(18)]);

        self::assertSame(['age' => 18], self::process($schema, 'oops')->values());
    }

    public function testADefaultMakesTheStructureOptional()
    {
        $schema = Schema::structure([
            'address' => Schema::structure(['city' => Schema::input()->required()])->default(null),
        ]);

        self::assertSame(['address' => null], self::process($schema, [])->data());
        self::assertSame(['address' => ['city' => 'required']], self::process($schema, ['address' => ['zip' => '1']])->errors());
    }

    public function testStructuresNest()
    {
        $schema = Schema::structure([
            'name' => Schema::input(),
            'address' => Schema::structure([
                'city' => Schema::input()->required(),
                'zip' => Schema::input()->pattern('\d{5}'),
            ]),
        ]);

        $result = self::process($schema, ['address' => ['zip' => '7500']]);

        self::assertSame(['address' => ['city' => 'required', 'zip' => 'pattern']], $result->errors());
        self::assertSame(['name' => null, 'address' => ['city' => null, 'zip' => '7500']], $result->values());
    }

    public function testAnAbsentNestedStructureStillAppliesItsDefaults()
    {
        $schema = Schema::structure([
            'options' => Schema::structure(['newsletter' => Schema::bool()->default(false)]),
        ]);

        self::assertSame(['options' => ['newsletter' => false]], self::process($schema, [])->data());
    }

    // -------------------------------------------------------------------------
    // assert
    // -------------------------------------------------------------------------

    private static function period(): Schema
    {
        return Schema::structure([
            'start' => Schema::date()->required(),
            'end' => Schema::date()->required(),
        ]);
    }

    public function testAnAssertReceivesTheSanitizedValues()
    {
        $seen = null;
        $schema = self::period()->assert(function (array $v) use (&$seen) {
            $seen = $v;
            return true;
        });

        self::process($schema, ['start' => ' 2026-10-01 ', 'end' => '2026-10-15', 'other' => 1]);

        self::assertSame(['start' => '2026-10-01', 'end' => '2026-10-15'], $seen);
    }

    public function testAnAssertErrorGoesOnTheGivenField()
    {
        $schema = self::period()->assert(fn(array $v) => $v['end'] >= $v['start'], 'before_start', 'end');

        self::assertSame(['end' => 'before_start'], self::process($schema, ['start' => '2026-10-15', 'end' => '2026-10-01'])->errors());
        self::assertTrue(self::process($schema, ['start' => '2026-10-01', 'end' => '2026-10-15'])->isValid());
    }

    public function testAnAssertWithoutFieldPutsItsCodeOnTheStructure()
    {
        $schema = Schema::structure(['period' => self::period()->assert(fn() => false, 'order')]);

        self::assertSame(['period' => 'order'], self::process($schema, ['period' => ['start' => '2026-10-01', 'end' => '2026-10-15']])->errors());
    }

    public function testARootAssertWithoutFieldIsUnderTheEmptyKey()
    {
        $schema = self::period()->assert(fn() => false, 'order');

        self::assertSame(['' => 'order'], self::process($schema, ['start' => '2026-10-01', 'end' => '2026-10-15'])->errors());
    }

    public function testAnAssertOnlyRunsWhenAllItemsAreValid()
    {
        $called = false;
        $schema = self::period()->assert(function () use (&$called) {
            $called = true;
            return true;
        });

        $result = self::process($schema, ['start' => '2026-10-01']);

        self::assertFalse($called);
        self::assertSame(['end' => 'required'], $result->errors());
    }

    public function testAnAssertCanFailWithItsOwnCode()
    {
        $schema = self::period()->assert(fn() => Schema::fail('closed'), field: 'start');

        self::assertSame(['start' => 'closed'], self::process($schema, ['start' => '2026-10-01', 'end' => '2026-10-15'])->errors());
        self::assertSame(['' => 'closed'], self::process(self::period()->assert(fn() => Schema::fail('closed')), ['start' => '2026-10-01', 'end' => '2026-10-15'])->errors());
    }

    public function testTheFirstFailingAssertWins()
    {
        $schema = self::period()
            ->assert(fn() => false, 'first', 'start')
            ->assert(fn() => false, 'second', 'end');

        self::assertSame(['start' => 'first'], self::process($schema, ['start' => '2026-10-01', 'end' => '2026-10-15'])->errors());
    }

    // -------------------------------------------------------------------------
    // transform / castTo
    // -------------------------------------------------------------------------

    public function testDataIsAnArrayByDefault()
    {
        self::assertIsArray(self::process(self::user(), ['name' => 'Tom'])->data());
    }

    public function testCastToAClassWithAConstructorPassesNamedArguments()
    {
        $user = self::process(self::user()->castTo(StructureTestUser::class), ['age' => '30', 'name' => 'Tom'])->data();

        self::assertInstanceOf(StructureTestUser::class, $user);
        self::assertSame('Tom', $user->name);
        self::assertSame(30, $user->age);
    }

    public function testCastToAClassWithoutAConstructorAssignsProperties()
    {
        $bag = self::process(self::user()->castTo(StructureTestBag::class), ['name' => 'Tom', 'age' => '30'])->data();

        self::assertInstanceOf(StructureTestBag::class, $bag);
        self::assertSame('Tom', $bag->name);
        self::assertSame(30, $bag->age);
    }

    public function testCastToObjectGivesAStdClass()
    {
        $data = self::process(self::user()->castTo('object'), ['name' => 'Tom'])->data();

        self::assertEquals((object) ['name' => 'Tom', 'age' => null], $data);
    }

    public function testItemsAreTransformedBeforeTheStructure()
    {
        $schema = Schema::structure([
            'date' => Schema::date()->castTo(DateTimeImmutable::class),
        ])->transform(fn(array $v) => $v['date']->format('d/m/Y'));

        $result = self::process($schema, ['date' => '2026-10-15']);

        self::assertSame('15/10/2026', $result->data());
        self::assertSame(['date' => '2026-10-15'], $result->values());
    }

    public function testAFieldWithoutAConstructorParameterIsAnError()
    {
        $schema = Schema::structure([
            'name' => Schema::input(),
            'age' => Schema::int(),
            'extra' => Schema::input(),
        ])->castTo(StructureTestUser::class);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Unknown named parameter $extra');

        self::process($schema, ['name' => 'Tom', 'age' => 1]);
    }

    public function testItemsReturnsTheSchemas()
    {
        self::assertSame(['name', 'age'], array_keys(self::user()->items()));
    }
}
