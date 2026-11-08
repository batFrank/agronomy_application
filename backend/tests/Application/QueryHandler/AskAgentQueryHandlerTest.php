<?php

declare(strict_types=1);

namespace App\Tests\Application\QueryHandler;

use App\Application\AI\AgentAnswer;
use App\Application\AI\AgentConversation;
use App\Application\AI\AgentGateway;
use App\Application\AI\AgentUnavailableException;
use App\Application\Query\AskAgent;
use App\Application\QueryHandler\AskAgentQueryHandler;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Mother\Application\Security\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AskAgentQueryHandler::class)]
class AskAgentQueryHandlerTest extends TestCase
{
    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     * @throws AgentUnavailableException
     * @throws SecurityException
     */
    public function testAsksTheGatewayOnBehalfOfTheActorOfTheQuery(): void
    {
        // Arrange
        $actor = UserMother::create();
        $answer = AgentAnswer::ofText('Three.');
        $conversation = AgentConversation::ofQuestion('How many users?');
        $gateway = $this->createMock(AgentGateway::class);
        $gateway->expects(self::once())->method('ask')->with($actor, self::identicalTo($conversation))->willReturn($answer);

        // Act
        $result = (new AskAgentQueryHandler($gateway))->handle(new AskAgent($actor, $conversation));

        // Assert
        self::assertSame($answer, $result);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testAGatewayFailureReachesTheCaller(): void
    {
        // Arrange
        $gateway = $this->createStub(AgentGateway::class);
        $gateway->method('ask')->willThrowException(AgentUnavailableException::fromProviderFailure(new \RuntimeException('The provider failed.')));

        // Act / Assert
        $this->expectException(AgentUnavailableException::class);

        (new AskAgentQueryHandler($gateway))->handle(new AskAgent(UserMother::create(), AgentConversation::ofQuestion('Hi')));
    }
}
