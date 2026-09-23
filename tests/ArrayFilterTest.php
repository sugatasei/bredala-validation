<?php

use Bredala\Validation\Filters\ArrayFilter;
use Bredala\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class ArrayFieldTest extends TestCase
{
    // -------------------------------------------------------------------------
    // sanitize
    // -------------------------------------------------------------------------

    public function testSanitizeKeepsANonEmptyArrayAsIs()
    {
        self::assertSame([1, 2], ArrayFilter::sanitize([1, 2]));
    }

    public function testSanitizePreservesKeys()
    {
        self::assertSame(['a' => 1], ArrayFilter::sanitize(['a' => 1]));
    }

    /**
     * @dataProvider emptyProvider
     */
    public function testSanitizeTurnsEmptyValuesIntoNull(mixed $input)
    {
        // Note: an empty array becomes null, not [].
        self::assertNull(ArrayFilter::sanitize($input));
    }

    public static function emptyProvider(): array
    {
        return [
            'empty array' => [[]],
            'null' => [null],
            'empty string' => [''],
            'blank string' => ['   '],
        ];
    }

    /**
     * @dataProvider rejectProvider
     */
    public function testSanitizeRejectsNonArrayScalars(mixed $input)
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('type');

        ArrayFilter::sanitize($input);
    }

    public static function rejectProvider(): array
    {
        return [
            'word' => ['x'],
            'zero' => [0],
            'int' => [42],
            'true' => [true],
            'false' => [false],
        ];
    }

    // -------------------------------------------------------------------------
    // map
    // -------------------------------------------------------------------------

    public function testMapAppliesTheCallbackToEveryElement()
    {
        self::assertSame([2, 4], ArrayFilter::map([1, 2], fn(int $v) => $v * 2));
    }

    public function testMapPreservesKeys()
    {
        self::assertSame(['a' => 2], ArrayFilter::map(['a' => 1], fn(int $v) => $v * 2));
    }

    public function testMapReturnsNullForAnEmptyInput()
    {
        self::assertNull(ArrayFilter::map([], fn($v) => $v));
    }

    public function testMapPropagatesAnElementError()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('type');

        ArrayFilter::mapInteger(['1', 'abc']);
    }

    /**
     * @dataProvider mapperProvider
     */
    public function testTypedMappers(string $mapper, array $input, array $expected)
    {
        self::assertSame($expected, ArrayFilter::{$mapper}($input));
    }

    public static function mapperProvider(): array
    {
        return [
            'mapInteger' => ['mapInteger', ['1', 2, '3'], [1, 2, 3]],
            'mapBoolean' => ['mapBoolean', ['yes', 0, true], [true, false, true]],
            'mapDecimal' => ['mapDecimal', ['1.5', '2.9'], [1.5, 2.9]],
            'mapInput' => ['mapInput', ['  a  ', "b\nc"], ['a', 'b c']],
            'mapText' => ['mapText', ["a\nb"], ["a\nb"]],
            'mapArray' => ['mapArray', [[1], [2]], [[1], [2]]],
        ];
    }

    public function testMapArrayTurnsEmptyNestedArraysIntoNull()
    {
        self::assertSame([null, [1]], ArrayFilter::mapArray([[], [1]]));
    }
}
