<?php

use Bredala\Validation\Rules\StringRule;
use Bredala\Validation\Schema;
use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{
    private static function errors(Schema $schema, string $value): array
    {
        return $schema->validate($value)->errors();
    }

    /**
     * @dataProvider validDateProvider
     */
    public function testDateAcceptsAValidDate(string $value, string $format)
    {
        self::assertSame([], self::errors(Schema::date($format), $value));
    }

    public static function validDateProvider(): array
    {
        return [
            'default format' => ['2026-10-15', 'Y-m-d'],
            'leap day' => ['2028-02-29', 'Y-m-d'],
            'custom format' => ['15/10/2026', 'd/m/Y'],
            'format with !' => ['2026-10-15', '!Y-m-d'],
        ];
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function testDateRejectsAnInvalidDate(string $value, string $format = 'Y-m-d')
    {
        self::assertSame(['' => 'date'], self::errors(Schema::date($format), $value));
    }

    public static function invalidDateProvider(): array
    {
        return [
            'overflowing day' => ['2026-02-30'],
            'not a leap year' => ['2026-02-29'],
            'month 13' => ['2026-13-01'],
            'missing leading zeros' => ['2026-1-5'],
            'relative format' => ['tomorrow'],
            'with a time' => ['2026-10-15 14:30'],
            'other format' => ['15/10/2026'],
            'wrong custom format' => ['2026-10-15', 'd/m/Y'],
            'garbage' => ['abc'],
        ];
    }

    /**
     * @dataProvider validDatetimeProvider
     */
    public function testDatetimeAcceptsIso8601ByDefault(string $value)
    {
        self::assertSame([], self::errors(Schema::datetime(), $value));
    }

    public static function validDatetimeProvider(): array
    {
        return [
            'positive offset' => ['2026-10-15T14:30:00+02:00'],
            'negative offset' => ['2026-10-15T14:30:00-05:00'],
            'zulu' => ['2026-10-15T14:30:00Z'],
            'zero offset' => ['2026-10-15T14:30:00+00:00'],
        ];
    }

    /**
     * @dataProvider invalidDatetimeProvider
     */
    public function testDatetimeRejectsAnythingElseByDefault(string $value)
    {
        self::assertSame(['' => 'datetime'], self::errors(Schema::datetime(), $value));
    }

    public static function invalidDatetimeProvider(): array
    {
        return [
            'no offset' => ['2026-10-15T14:30:00'],
            'no seconds' => ['2026-10-15T14:30+02:00'],
            'space separator' => ['2026-10-15 14:30:00+02:00'],
            'offset without colon' => ['2026-10-15T14:30:00+0200'],
            'overflowing day' => ['2026-02-30T14:30:00+02:00'],
            'hour 25' => ['2026-10-15T25:30:00+02:00'],
            'date only' => ['2026-10-15'],
            'relative format' => ['now'],
        ];
    }

    public function testDatetimeAcceptsACustomFormat()
    {
        // <input type="datetime-local"> sends no seconds and no offset.
        self::assertSame([], self::errors(Schema::datetime('Y-m-d\TH:i'), '2026-10-15T14:30'));
        self::assertSame(['' => 'datetime'], self::errors(Schema::datetime('Y-m-d\TH:i'), '2026-10-15T14:30:00+02:00'));
    }

    /**
     * @dataProvider validTimeProvider
     */
    public function testTimeAcceptsHoursMinutesAndOptionalSeconds(string $value)
    {
        self::assertSame([], self::errors(Schema::time(), $value));
    }

    public static function validTimeProvider(): array
    {
        return [
            'hh:mm' => ['14:30'],
            'hh:mm:ss' => ['14:30:15'],
            'midnight' => ['00:00'],
            'last second' => ['23:59:59'],
        ];
    }

    /**
     * @dataProvider invalidTimeProvider
     */
    public function testTimeRejectsAnInvalidTime(string $value)
    {
        self::assertSame(['' => 'time'], self::errors(Schema::time(), $value));
    }

    public static function invalidTimeProvider(): array
    {
        return [
            'hour 24' => ['24:00'],
            'minute 60' => ['14:60'],
            'second 60' => ['14:30:60'],
            'missing leading zero' => ['9:05'],
            'hours only' => ['14'],
            'with milliseconds' => ['14:30:15.123'],
            'am/pm' => ['2:30 PM'],
            'garbage' => ['abc'],
        ];
    }

    public function testDefaultFormatsCanBeCastToDateTimeImmutable()
    {
        $process = fn(Schema $schema, string $v) => $schema->castTo(DateTimeImmutable::class)->validate($v)->data();

        self::assertSame('2026-10-15', $process(Schema::date(), '2026-10-15')->format('Y-m-d'));
        self::assertSame('14:30:00+02:00', $process(Schema::datetime(), '2026-10-15T14:30:00+02:00')->format('H:i:sP'));
        self::assertSame('14:30:00', $process(Schema::time(), '14:30')->format('H:i:s'));
    }

    public function testIsDateIsUsableOnItsOwn()
    {
        self::assertTrue(StringRule::isDate('14:30', 'H:i', 'H:i:s'));
        self::assertFalse(StringRule::isDate('2026-02-30', 'Y-m-d'));
    }
}
