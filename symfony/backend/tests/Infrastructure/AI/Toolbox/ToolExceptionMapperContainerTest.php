<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\NotFoundException;
use App\Domain\User\Exception\UserForbiddenException;
use App\Infrastructure\AI\Toolbox\ToolExceptionMapper;
use App\Infrastructure\Validator\ValidatorException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[CoversClass(ToolExceptionMapper::class)]
class ToolExceptionMapperContainerTest extends KernelTestCase
{
    /**
     * Fails if the translators are not injected through the tag: without them every exception
     * would fall back to the generic message.
     *
     * @param non-empty-string $expectedStart
     */
    #[DataProvider('domainExceptionProvider')]
    public function testContainerTranslatesEveryFamilyInsteadOfGenericMessage(\Throwable $exception, string $expectedStart): void
    {
        // Arrange
        self::bootKernel();
        $mapper = self::getContainer()->get('test.tool_exception_mapper');
        self::assertInstanceOf(ToolExceptionMapper::class, $mapper);

        // Act
        $result = $mapper->toToolCallException($exception);

        // Assert
        self::assertStringStartsWith($expectedStart, $result->getToolCallResult());
        self::assertNotSame('The operation could not be completed.', $result->getToolCallResult());
    }

    public static function domainExceptionProvider(): \Generator
    {
        yield 'not found family' => [
            new NotFoundException('Missing'),
            'The requested resource was not found.',
        ];

        yield 'forbidden family' => [
            UserForbiddenException::cannotViewUser(),
            'The current user is not allowed to perform this operation.',
        ];

        yield 'denied update (the permission of #122)' => [
            UserForbiddenException::cannotUpdateUser(),
            'The current user is not allowed to perform this operation.',
        ];

        yield 'unprocessable entity family' => [
            new InvalidEmailException('not-an-email'),
            'The operation was rejected by a business rule.',
        ];

        yield 'validator family' => [
            ValidatorException::createByViolations(new ConstraintViolationList([
                new ConstraintViolation('This value is too short.', null, [], null, 'city', 'Bil'),
            ])),
            'The input is not valid.',
        ];
    }
}
