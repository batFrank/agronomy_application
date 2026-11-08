<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Toolbox;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\User\UserRole;
use App\Infrastructure\AI\Toolbox\IdentifierStrippingToolbox;
use App\Infrastructure\AI\Toolbox\ToolPayloadSanitizer;
use App\Infrastructure\Transformer\DateTimeTransformer;
use App\Tests\Infrastructure\AI\HelperTrait\ToolboxHelperTrait;
use App\Tests\Infrastructure\AI\Toolbox\Double\ProbeArrayTool;
use App\Tests\Mother\Domain\Shared\Email\EmailMother;
use App\Tests\Mother\Domain\Shared\UserCard\UserCardMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use App\Tests\Mother\Domain\User\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(IdentifierStrippingToolbox::class)]
#[CoversClass(ToolPayloadSanitizer::class)]
class IdentifierStrippingToolboxEndToEndTest extends KernelTestCase
{
    use ToolboxHelperTrait;

    /**
     * Fails if any identifier of the transformer output reaches the model through the real toolbox and converter.
     *
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testModelReceivesTransformerOutputWithoutIdentifiers(): void
    {
        // Arrange
        self::bootKernel();
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
        $toolbox = $this->createToolbox(new ProbeArrayTool($user));

        // Act
        $result = $this->callTool($toolbox, 'probe_array');

        // Assert
        $this->assertSame(json_encode([
            'email' => 'user@example.com',
            'roles' => ['ROLE_USER'],
            'created_at' => DateTimeTransformer::toOutput($createdAt),
            'updated_at' => DateTimeTransformer::toOutput($updatedAt),
            'creator' => ['email' => 'creator@example.com'],
        ]), $result);
    }
}
