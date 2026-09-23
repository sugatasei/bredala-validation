<?php

use Bredala\Validation\Schema;
use Bredala\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class ValidationExceptionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // fail
    // -------------------------------------------------------------------------

    public function testFailThrowsAValidationExceptionCarryingTheCodeAsMessage()
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('my_code');

        Schema::fail('my_code');
    }

    public function testValidationExceptionIsAnException()
    {
        self::assertInstanceOf(Exception::class, new ValidationException('x'));
    }

}
