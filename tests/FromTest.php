<?php

use Bredala\Validation\Result;
use Bredala\Validation\Schema;
use PHPUnit\Framework\TestCase;

enum FromTestStatus: string
{
    case Draft = 'draft';
    case Paid = 'paid';
}

final class FromTestAddress
{
    public function __construct(
        public readonly string $city,
        public readonly ?string $zip = null,
    ) {
    }
}

final class FromTestLine
{
    public function __construct(
        public readonly string $sku,
        public readonly int $qty = 1,
    ) {
    }
}

final class FromTestOrder
{
    public function __construct(
        public readonly string $ref,
        public readonly FromTestStatus $status,
        public readonly FromTestAddress $address,
        public readonly float $total,
        public readonly bool $paid,
        public readonly array $lines = [],
        public readonly ?string $note = null,
        public readonly mixed $meta = null,
    ) {
    }
}

class FromTestBag
{
    public string $name;
    public ?int $age = null;
}

final class FromTestUnion
{
    public function __construct(public readonly int|string $id)
    {
    }
}

final class FromTestEvent
{
    public function __construct(public readonly DateTimeImmutable $date)
    {
    }
}

final class FromTestDefaults
{
    public function __construct(
        public readonly ?FromTestAddress $billing,
        public readonly FromTestStatus $status = FromTestStatus::Draft,
        public readonly ?FromTestAddress $address = null,
    ) {
    }
}

class FromTest extends TestCase
{
    private static function process(Schema $schema, mixed $value): Result
    {
        return $schema->validate($value);
    }

    private static function order(): array
    {
        return [
            'ref' => ' R1 ',
            'status' => 'paid',
            'address' => ['city' => 'Paris'],
            'total' => '9.5',
            'paid' => 'on',
        ];
    }

    public function testFromBuildsAStructureCastToTheClass()
    {
        $order = self::process(Schema::from(FromTestOrder::class), self::order())->data();

        self::assertInstanceOf(FromTestOrder::class, $order);
        self::assertSame('R1', $order->ref);
        self::assertSame(FromTestStatus::Paid, $order->status);
        self::assertSame(9.5, $order->total);
        self::assertTrue($order->paid);
        self::assertSame([], $order->lines);
        self::assertNull($order->note);
    }

    public function testNestedClassesBecomeNestedStructures()
    {
        $order = self::process(Schema::from(FromTestOrder::class), self::order())->data();

        self::assertInstanceOf(FromTestAddress::class, $order->address);
        self::assertSame('Paris', $order->address->city);
    }

    public function testParametersWithoutDefaultAndNotNullableAreRequired()
    {
        $result = self::process(Schema::from(FromTestOrder::class), []);

        self::assertSame([
            'ref' => 'required',
            'status' => 'required',
            'address' => ['city' => 'required'],
            'total' => 'required',
            'paid' => 'required',
        ], $result->errors());
    }

    public function testDefaultValuesAreUsed()
    {
        $line = self::process(Schema::from(FromTestLine::class), ['sku' => 'A'])->data();

        self::assertSame(1, $line->qty);
    }

    public function testItemsOverrideTheGeneratedSchemas()
    {
        $schema = Schema::from(FromTestOrder::class, [
            'lines' => Schema::arrayOf(Schema::from(FromTestLine::class))->min(1),
        ]);

        self::assertSame(['lines' => 'min'], self::process($schema, self::order())->errors());

        $order = self::process($schema, self::order() + ['lines' => [['sku' => 'A', 'qty' => '3']]])->data();

        self::assertInstanceOf(FromTestLine::class, $order->lines[0]);
        self::assertSame(3, $order->lines[0]->qty);
    }

    public function testPublicPropertiesAreUsedWithoutAConstructor()
    {
        $bag = self::process(Schema::from(FromTestBag::class), ['name' => 'Tom', 'age' => '30'])->data();

        self::assertInstanceOf(FromTestBag::class, $bag);
        self::assertSame('Tom', $bag->name);
        self::assertSame(30, $bag->age);
        self::assertSame(['name' => 'required'], self::process(Schema::from(FromTestBag::class), [])->errors());
    }

    public function testAUnionTypeNeedsAnExplicitSchema()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('union types need an explicit schema');

        Schema::from(FromTestUnion::class);
    }

    public function testAUnionTypeCanBeGivenAnExplicitSchema()
    {
        $schema = Schema::from(FromTestUnion::class, ['id' => Schema::anyOf(Schema::int(), Schema::input())]);

        self::assertSame(42, self::process($schema, ['id' => '42'])->data()->id);
    }

    public function testADateNeedsAnExplicitSchema()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('type DateTimeImmutable needs an explicit schema');

        Schema::from(FromTestEvent::class);
    }

    public function testADateCanBeGivenAnExplicitSchema()
    {
        $schema = Schema::from(FromTestEvent::class, [
            'date' => Schema::date()->required()->castTo(DateTimeImmutable::class),
        ]);

        self::assertSame('2026-10-15', self::process($schema, ['date' => '2026-10-15'])->data()->date->format('Y-m-d'));
    }

    public function testAnEnumDefaultIsUsed()
    {
        $result = self::process(Schema::from(FromTestDefaults::class), []);

        self::assertSame('draft', $result->values()['status']);
        self::assertSame(FromTestStatus::Draft, $result->data()->status);
    }

    public function testANullableNestedClassCanBeOmitted()
    {
        $result = self::process(Schema::from(FromTestDefaults::class), []);

        self::assertTrue($result->isValid());
        self::assertNull($result->data()->address);
        self::assertNull($result->data()->billing);
    }

    public function testANullableNestedClassIsValidatedWhenGiven()
    {
        $result = self::process(Schema::from(FromTestDefaults::class), ['address' => ['zip' => '75001']]);

        self::assertSame(['address' => ['city' => 'required']], $result->errors());
    }
}
