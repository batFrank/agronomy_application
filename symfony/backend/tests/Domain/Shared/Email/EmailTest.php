<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shared\Email;

use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\DomainException;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    #[DataProvider('invalidEmailProvider')]
    public function testInvalidEmailThrowsInvalidEmailException(string $invalidEmail): void
    {
        $this->expectException(InvalidEmailException::class);

        new Email($invalidEmail);
    }

    public function testInvalidEmailExceptionExtendsDomainException(): void
    {
        $exception = new InvalidEmailException('invalid@');

        $this->assertInstanceOf(DomainException::class, $exception);
    }

    /**
     * A subclass overriding value() could return '' and reach SecurityException::invalidUserIdentifier():
     * sealing the class is what makes that guard unreachable by types.
     */
    public function testEmailIsFinal(): void
    {
        $this->assertTrue((new \ReflectionClass(Email::class))->isFinal());
    }

    /**
     * @throws InvalidEmailException
     */
    public function testCreateValidEmail(): void
    {
        $email = EmailMother::create('user@example.com');

        $this->assertSame('user@example.com', $email->value());
        $this->assertSame('user@example.com', (string) $email);
    }

    /**
     * @throws InvalidEmailException
     */
    public function testEquals(): void
    {
        $email1 = EmailMother::create('user@example.com');
        $email2 = EmailMother::create('user@example.com');
        $email3 = EmailMother::create('other@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }

    public static function invalidEmailProvider(): \Generator
    {
        yield 'plain string' => ['invalid-email'];
        yield 'missing tld' => ['user@domain'];
        yield 'missing user' => ['@domain.com'];
        yield 'empty string' => [''];
    }
}
