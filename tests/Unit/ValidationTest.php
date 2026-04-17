<?php

use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testValidateIntAcceptsNumericString(): void
    {
        $this->assertSame(42, validateInt('42'));
    }

    public function testValidateIntAcceptsZero(): void
    {
        $this->assertSame(0, validateInt('0'));
    }

    public function testValidateIntAcceptsNegative(): void
    {
        $this->assertSame(-7, validateInt('-7'));
    }

    public function testValidateIntRejectsAlpha(): void
    {
        $this->assertNull(validateInt('abc'));
    }

    public function testValidateIntRejectsFloat(): void
    {
        $this->assertNull(validateInt('3.14'));
    }

    public function testValidatePositiveIntRejectsZero(): void
    {
        $this->assertNull(validatePositiveInt('0'));
    }

    public function testValidatePositiveIntRejectsNegative(): void
    {
        $this->assertNull(validatePositiveInt('-1'));
    }

    public function testValidatePositiveIntAcceptsPositive(): void
    {
        $this->assertSame(5, validatePositiveInt('5'));
    }

    public function testValidateStringTrimsWhitespace(): void
    {
        $this->assertSame('hello', validateString('  hello  '));
    }

    public function testValidateStringRejectsEmpty(): void
    {
        $this->assertNull(validateString(''));
        $this->assertNull(validateString('   '));
    }

    public function testValidateStringRejectsOverLength(): void
    {
        $this->assertNull(validateString(str_repeat('a', 300), 255));
    }

    public function testValidateStringRejectsNonString(): void
    {
        $this->assertNull(validateString(42));
        $this->assertNull(validateString(null));
        $this->assertNull(validateString(['x']));
    }

    public function testValidateEmailAcceptsValid(): void
    {
        $this->assertSame('a@b.co', validateEmail('a@b.co'));
    }

    public function testValidateEmailRejectsInvalid(): void
    {
        $this->assertNull(validateEmail('not-an-email'));
        $this->assertNull(validateEmail('a@'));
        $this->assertNull(validateEmail(''));
    }

    public function testValidateDateAcceptsDashFormat(): void
    {
        $this->assertSame('2026-04-17', validateDate('2026-04-17'));
    }

    public function testValidateDateAcceptsSlashFormat(): void
    {
        $this->assertSame('2026/04/17', validateDate('2026/04/17'));
    }

    public function testValidateDateRejectsUnparseable(): void
    {
        $this->assertNull(validateDate('not-a-date'));
        $this->assertNull(validateDate('26/04/17'));
    }

    public function testValidateInArrayAcceptsMember(): void
    {
        $this->assertSame('ghom', validateInArray('ghom', ['ghom', 'pardis']));
    }

    public function testValidateInArrayRejectsNonMember(): void
    {
        $this->assertNull(validateInArray('tehran', ['ghom', 'pardis']));
    }
}
