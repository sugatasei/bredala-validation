<?php

use Bredala\Validation\Result;
use Bredala\Validation\Schema;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testValidateReturnsAResult()
    {
        self::assertInstanceOf(Result::class, Schema::input()->validate('x'));
    }

    public function testAValidResultHasNoErrors()
    {
        $result = Schema::structure(['a' => Schema::input()])->validate(['a' => 'x']);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->errors());
    }

    public function testDataOfAnInvalidResultThrows()
    {
        $result = Schema::input()->required()->validate(null);

        $this->expectException(LogicException::class);

        $result->data();
    }

    public function testValuesOfAnInvalidResultAreStillReadable()
    {
        $result = Schema::structure(['a' => Schema::int()->min(5)])->validate(['a' => '3']);

        self::assertFalse($result->isValid());
        self::assertSame(['a' => 3], $result->values());
    }

    public function testErrorReturnsTheRawError()
    {
        self::assertNull(Schema::input()->validate('x')->error());
        self::assertSame('required', Schema::input()->required()->validate(null)->error());
        self::assertSame(['a' => 'min'], Schema::structure(['a' => Schema::int()->min(5)])->validate(['a' => '3'])->error());
    }

    public function testAScalarRootErrorIsUnderTheEmptyKey()
    {
        self::assertSame(['' => 'required'], Schema::input()->required()->validate(null)->errors());
    }
}
