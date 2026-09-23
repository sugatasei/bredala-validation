<?php

use Bredala\Validation\Filters\BooleanFilter;
use Bredala\Validation\Filters\DecimalFilter;
use Bredala\Validation\Filters\IntegerFilter;
use Bredala\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class ScalarFieldTest extends TestCase
{
    // -------------------------------------------------------------------------
    // BooleanFilter::sanitize
    // -------------------------------------------------------------------------

    /**
     * @dataProvider booleanProvider
     */
    public function testBooleanSanitize(mixed $input, ?bool $expected)
    {
        self::assertSame($expected, BooleanFilter::sanitize($input));
    }

    public static function booleanProvider(): array
    {
        return [
            'null' => [null, null],
            'empty string' => ['', null],
            'blank string' => ['   ', null],
            'true' => [true, true],
            'false' => [false, false],
            'int 1' => [1, true],
            'int 0' => [0, false],
            'string 1' => ['1', true],
            'string 0' => ['0', false],
            'yes' => ['yes', true],
            'no' => ['no', false],
            'on' => ['on', true],
            'off' => ['off', false],
            'true string' => ['true', true],
            'false string' => ['false', false],
        ];
    }

    /**
     * @dataProvider booleanRejectProvider
     */
    public function testBooleanSanitizeRejectsOtherValues(mixed $input)
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('type');

        BooleanFilter::sanitize($input);
    }

    public static function booleanRejectProvider(): array
    {
        return [
            'word' => ['abc'],
            'int 2' => [2],
            'negative' => [-1],
            'array' => [[]],
        ];
    }



    // -------------------------------------------------------------------------
    // IntegerFilter::sanitize
    // -------------------------------------------------------------------------

    /**
     * @dataProvider integerProvider
     */
    public function testIntegerSanitize(mixed $input, ?int $expected)
    {
        self::assertSame($expected, IntegerFilter::sanitize($input));
    }

    public static function integerProvider(): array
    {
        return [
            'null' => [null, null],
            'empty string' => ['', null],
            'int' => [42, 42],
            'negative int' => [-7, -7],
            'numeric string' => ['42', 42],
            'padded string' => ['  7  ', 7],
            'zero' => [0, 0],
            'zero string' => ['0', 0],
            'float truncated' => [1.9, 1],
            'float string truncated' => ['1.9', 1],
            'exponent' => ['1e3', 1000],
        ];
    }

    /**
     * @dataProvider integerRejectProvider
     */
    public function testIntegerSanitizeRejectsNonNumerics(mixed $input)
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('type');

        IntegerFilter::sanitize($input);
    }

    public static function integerRejectProvider(): array
    {
        return [
            'word' => ['abc'],
            'hex string' => ['0x1A'],
            'trailing unit' => ['10px'],
            'array' => [[]],
            'true' => [true],
        ];
    }






    // -------------------------------------------------------------------------
    // DecimalFilter::sanitize
    // -------------------------------------------------------------------------

    /**
     * @dataProvider decimalProvider
     */
    public function testDecimalSanitizeKeepsDecimals(mixed $input, ?float $expected)
    {
        self::assertSame($expected, DecimalFilter::sanitize($input));
    }

    public static function decimalProvider(): array
    {
        return [
            'null' => [null, null],
            'empty string' => ['', null],
            'float' => [1.5, 1.5],
            'float string' => ['1.5', 1.5],
            'no rounding' => ['2.75', 2.75],
            'below one' => ['0.1', 0.1],
            'int becomes float' => [3, 3.0],
            'negative float' => [-1.5, -1.5],
        ];
    }

    /**
     * @dataProvider integerRejectProvider
     */
    public function testDecimalSanitizeRejectsNonNumerics(mixed $input)
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('type');

        DecimalFilter::sanitize($input);
    }





}
