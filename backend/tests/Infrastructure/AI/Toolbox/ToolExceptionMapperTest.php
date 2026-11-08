<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\DomainException;
use App\Domain\Shared\Exception\NotFoundException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Domain\User\Exception\UserNotFoundException;
use App\Infrastructure\AI\Toolbox\ToolCallException;
use App\Infrastructure\AI\Toolbox\ToolExceptionMapper;
use App\Infrastructure\AI\Toolbox\Translator\ForbiddenExceptionTranslator;
use App\Infrastructure\AI\Toolbox\Translator\NotFoundExceptionTranslator;
use App\Infrastructure\AI\Toolbox\Translator\UnprocessableEntityExceptionTranslator;
use App\Infrastructure\AI\Toolbox\Translator\ValidatorExceptionTranslator;
use App\Infrastructure\Validator\ValidatorException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[CoversClass(ToolExceptionMapper::class)]
class ToolExceptionMapperTest extends \PHPUnit\Framework\TestCase
{
    private const string GENERIC_MESSAGE = 'The operation could not be completed.';

    /**
     * @throws \LogicException
     */
    public function testValidatorTranslatorRefusesExceptionsItDoesNotSupport(): void
    {
        // Arrange
        $translator = new ValidatorExceptionTranslator();
        $exception = new \RuntimeException('SECRET_INTERNAL_DETAIL');

        // Assert
        self::expectException(\LogicException::class);

        // Act
        $translator->translate($exception);
    }

    public function testReturnsGenericMessageWhenNoTranslatorSupportsTheException(): void
    {
        // Arrange
        $mapper = $this->createMapper();
        $exception = new \RuntimeException('SECRET_INTERNAL_DETAIL');

        // Act
        $result = $mapper->toToolCallException($exception);

        // Assert
        self::assertSame(self::GENERIC_MESSAGE, $result->getToolCallResult());
        self::assertStringNotContainsString('SECRET_INTERNAL_DETAIL', $result->getToolCallResult());
        self::assertSame($exception, $result->getPrevious());
    }

    public function testReturnsIncomingToolCallExceptionUnchanged(): void
    {
        // Arrange
        $mapper = $this->createMapper();
        $incoming = ToolCallException::withReason('Already translated.', new NotFoundException('x'));

        // Act
        $result = $mapper->toToolCallException($incoming);

        // Assert
        self::assertSame($incoming, $result);
    }

    #[DataProvider('translatedExceptionProvider')]
    public function testTranslatesExceptionIntoActionableMessage(\Throwable $exception, string $expectedMessage): void
    {
        // Arrange
        $mapper = $this->createMapper();

        // Act
        $result = $mapper->toToolCallException($exception);

        // Assert
        self::assertSame($expectedMessage, $result->getToolCallResult());
        self::assertSame($exception, $result->getPrevious());
    }

    public static function translatedExceptionProvider(): \Generator
    {
        yield 'not found with reason' => [
            new NotFoundException('Product 42 does not exist'),
            'The requested resource was not found. Reason: Product 42 does not exist',
        ];

        yield 'user not found without reason' => [
            new UserNotFoundException(),
            'The requested resource was not found.',
        ];

        yield 'forbidden with reason' => [
            UserForbiddenException::cannotViewUser(),
            'The current user is not allowed to perform this operation. Reason: You are not authorized to view this user',
        ];

        yield 'forbidden with default message' => [
            new UserForbiddenException(),
            'The current user is not allowed to perform this operation.',
        ];

        yield 'unprocessable entity with default message' => [
            new DomainException(),
            'The operation was rejected by a business rule.',
        ];

        yield 'unprocessable entity through domain exception inheritance' => [
            new InvalidEmailException('not-an-email'),
            'The operation was rejected by a business rule. Reason: Invalid email address: not-an-email',
        ];

        yield 'validator exception by message' => [
            ValidatorException::createByMessage('Custom validation failure'),
            'Custom validation failure',
        ];

        yield 'validator exception by violations' => [
            ValidatorException::createByViolations(new ConstraintViolationList([
                new ConstraintViolation('This value is too short.', null, [], null, 'city', 'REJECTED_VALUE_XQ7'),
            ])),
            "The input is not valid.\ncity: This value is too short.",
        ];
    }

    public function testDoesNotExposeRejectedValueFromViolations(): void
    {
        // Arrange
        $mapper = $this->createMapper();
        $exception = ValidatorException::createByViolations(new ConstraintViolationList([
            new ConstraintViolation('This value is too short.', null, [], null, 'city', 'REJECTED_VALUE_XQ7'),
        ]));

        // Act
        $result = $mapper->toToolCallException($exception);

        // Assert
        self::assertStringNotContainsString('REJECTED_VALUE_XQ7', $result->getToolCallResult());
    }

    private function createMapper(): ToolExceptionMapper
    {
        return new ToolExceptionMapper([
            new NotFoundExceptionTranslator(),
            new ForbiddenExceptionTranslator(),
            new UnprocessableEntityExceptionTranslator(),
            new ValidatorExceptionTranslator(),
        ]);
    }
}
