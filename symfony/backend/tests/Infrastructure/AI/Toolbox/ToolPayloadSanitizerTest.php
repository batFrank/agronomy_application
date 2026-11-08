<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\UserRole;
use App\Infrastructure\AI\Toolbox\ToolPayloadSanitizer;
use App\Infrastructure\Transformer\DateTimeTransformer;
use App\Infrastructure\Transformer\User\UserTransformer;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\Shared\UserCard\UserCardMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolPayloadSanitizer::class)]
class ToolPayloadSanitizerTest extends TestCase
{
    private const string UUID = '3f2b8c1e-9a4d-4b7e-8c2a-1d5e6f7a8b9c';

    /**
     * Fails if created_by (a UUID value under a non-id name) or creator.id survive the real transformer output.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testRemovesIdentifiersFromTransformerOutputWithNestedCreator(): void
    {
        // Arrange
        $createdAt = new \DateTimeImmutable('2026-01-10 10:00:00');
        $updatedAt = new \DateTimeImmutable('2026-01-11 11:00:00');
        $user = UserMother::create(
            email: EmailMother::create('user@example.com'),
            roles: [UserRole::User],
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            createdBy: UuidMother::create(),
        );
        $user->setCreator(UserCardMother::create(email: 'creator@example.com'));

        // Act
        $result = ToolPayloadSanitizer::withoutIds(UserTransformer::toArray($user));

        // Assert
        $this->assertEquals([
            'email' => 'user@example.com',
            'roles' => ['ROLE_USER'],
            'created_at' => DateTimeTransformer::toOutput($createdAt),
            'updated_at' => DateTimeTransformer::toOutput($updatedAt),
            'creator' => ['email' => 'creator@example.com'],
        ], $result);
    }

    public function testReturnsEmptyArrayWhenPayloadIsEmpty(): void
    {
        // Act
        $result = ToolPayloadSanitizer::withoutIds([]);

        // Assert
        $this->assertSame([], $result);
    }

    public function testKeepsNonArrayValuesAsTheyAre(): void
    {
        // Arrange
        $object = new class {
            public int $id = 9;
        };
        $payload = [
            'nothing' => null,
            'count' => 5,
            'ratio' => 1.5,
            'flag' => false,
            'object' => $object,
        ];

        // Act
        $result = ToolPayloadSanitizer::withoutIds($payload);

        // Assert
        $this->assertSame($payload, $result);
    }

    public function testKeepsArrayWithoutIdentifiersUnchanged(): void
    {
        // Arrange
        $payload = ['email' => 'user@example.com', 'roles' => ['ROLE_USER']];

        // Act
        $result = ToolPayloadSanitizer::withoutIds($payload);

        // Assert
        $this->assertSame($payload, $result);
    }

    public function testKeepsNestedArrayEmptyWhenOnlyIdentifiersAreRemoved(): void
    {
        // Arrange
        $payload = ['user' => ['id' => 1, 'created_by' => self::UUID]];

        // Act
        $result = ToolPayloadSanitizer::withoutIds($payload);

        // Assert
        $this->assertSame(['user' => []], $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $expected
     */
    #[DataProvider('usefulFieldProvider')]
    public function testKeepsUsefulFieldsThatLookLikeIdentifiers(array $payload, array $expected): void
    {
        // Act
        $result = ToolPayloadSanitizer::withoutIds($payload);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public static function usefulFieldProvider(): \Generator
    {
        yield 'valid flag ends in id without separator' => [
            ['valid' => true],
            ['valid' => true],
        ];

        yield 'paid flag ends in id without separator' => [
            ['paid' => false],
            ['paid' => false],
        ];

        yield 'grid list ends in id without separator' => [
            ['grid' => [1, 2]],
            ['grid' => [1, 2]],
        ];

        yield 'identity label ends in id without separator' => [
            ['identity' => 'Operator'],
            ['identity' => 'Operator'],
        ];

        yield 'idle status ends in id without separator' => [
            ['idle' => true],
            ['idle' => true],
        ];

        yield 'sort_by field' => [
            ['sort_by' => 'name'],
            ['sort_by' => 'name'],
        ];

        yield 'order_by field' => [
            ['order_by' => 'created_at'],
            ['order_by' => 'created_at'],
        ];

        yield 'email field' => [
            ['email' => 'user@example.com'],
            ['email' => 'user@example.com'],
        ];

        yield 'description containing a uuid inside text' => [
            ['description' => 'See ' . self::UUID . ' for details'],
            ['description' => 'See ' . self::UUID . ' for details'],
        ];

        yield 'uuid-like value that is not an exact uuid' => [
            ['code' => '3f2b8c1e-9a4d-4b7e-8c2a-1d5e6f7a8b9'],
            ['code' => '3f2b8c1e-9a4d-4b7e-8c2a-1d5e6f7a8b9'],
        ];

        yield 'camel case key is not snake_case' => [
            ['userId' => 1],
            ['userId' => 1],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $expected
     */
    #[DataProvider('identifierProvider')]
    public function testRemovesIdentifierKeysAndUuidValuesAtAnyDepth(array $payload, array $expected): void
    {
        // Act
        $result = ToolPayloadSanitizer::withoutIds($payload);

        // Assert
        $this->assertEquals($expected, $result);
    }

    public static function identifierProvider(): \Generator
    {
        yield 'id key' => [
            ['id' => 1, 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'user_id key' => [
            ['user_id' => 1, 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'progress_id key' => [
            ['progress_id' => 1, 'score' => 3],
            ['score' => 3],
        ];

        yield 'tax_id key' => [
            ['tax_id' => 'B12345678', 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'national_id key' => [
            ['national_id' => '12345678Z', 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'external_id key' => [
            ['external_id' => 'ext-7', 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'uuid key' => [
            ['uuid' => self::UUID, 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'user_uuid key' => [
            ['user_uuid' => self::UUID, 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'created_by with lowercase uuid value' => [
            ['created_by' => self::UUID, 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'owner with uppercase uuid value' => [
            ['owner' => strtoupper(self::UUID), 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'any key with uuid value' => [
            ['reference_point' => self::UUID, 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];
    }

    public function testKeepsNumericKeysWithoutReindexing(): void
    {
        // Arrange
        $payload = [3 => 'first', 7 => 'second'];

        // Act
        $result = ToolPayloadSanitizer::withoutIds($payload);

        // Assert
        $this->assertSame([3 => 'first', 7 => 'second'], $result);
    }

    public function testSanitizesArraysInsideListWithoutReindexing(): void
    {
        // Arrange
        $payload = [
            3 => ['id' => 1, 'name' => 'first'],
            7 => ['user_id' => 2, 'name' => 'second'],
        ];

        // Act
        $result = ToolPayloadSanitizer::withoutIds($payload);

        // Assert
        $this->assertSame([
            3 => ['name' => 'first'],
            7 => ['name' => 'second'],
        ], $result);
    }

    /**
     * @param array<array-key, mixed> $payload
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('uuidListProvider')]
    public function testRemovesAKeyWhoseValueIsAListOfExactUuids(array $payload, array $expected): void
    {
        // Act
        $result = ToolPayloadSanitizer::withoutIds($payload);

        // Assert
        $this->assertSame($expected, $result);
    }

    public static function uuidListProvider(): \Generator
    {
        yield 'list of uuids under a name the pattern does not catch' => [
            ['user_ids' => [self::UUID, strtoupper(self::UUID)], 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'list with a single uuid' => [
            ['members' => [self::UUID], 'name' => 'Ana'],
            ['name' => 'Ana'],
        ];

        yield 'list of uuids nested in another level' => [
            ['team' => ['member_refs' => [self::UUID, self::UUID], 'name' => 'Ana']],
            ['team' => ['name' => 'Ana']],
        ];

        yield 'mixed list is kept whole' => [
            ['members' => [self::UUID, 'Ana']],
            ['members' => [self::UUID, 'Ana']],
        ];

        yield 'empty list is kept' => [
            ['members' => []],
            ['members' => []],
        ];

        yield 'list of texts that only contain a uuid is kept' => [
            ['notes' => ['See ' . self::UUID]],
            ['notes' => ['See ' . self::UUID]],
        ];

        yield 'list with an array element is kept and sanitized' => [
            ['members' => [self::UUID, ['id' => 1, 'name' => 'Ana']]],
            ['members' => [self::UUID, ['name' => 'Ana']]],
        ];
    }

    /**
     * Documents a known limit: a UUID element of a list with no key of its own (the root payload, or a list
     * inside another list) is kept, because there is no key to remove it by.
     */
    public function testKeepsUuidElementsOfAListWithoutKeyAsKnownLimit(): void
    {
        // Arrange
        $payload = [self::UUID, [self::UUID]];

        // Act
        $result = ToolPayloadSanitizer::withoutIds($payload);

        // Assert
        $this->assertSame([self::UUID, [self::UUID]], $result);
    }
}
