<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent\Report;

use App\Application\AI\AgentUnavailableException;
use App\Application\AI\Report;
use App\Application\AI\ReportGateway;
use App\Application\Security\User as SecurityUser;
use App\Infrastructure\AI\Agent\SymfonyAiReportGateway;
use App\Tests\Infrastructure\AI\Agent\Double\ScriptedPlatform;
use App\Tests\Mother\Application\Security\UserMother;
use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The port as the container wires it: the real agent and the validator of the framework, not a gateway
 * built by hand with a validator of the test. If that validator did not read the attributes of the DTO,
 * the constraints would stop being checked in production and no other test would notice.
 *
 * The container's platform is the ScriptedPlatform, which replaces the package's structured output, so it
 * is scripted to answer the object result the package would have handed over for a text.
 */
class ReportGatewayWiringTest extends KernelTestCase
{
    private ScriptedPlatform $platform;

    private ReportGateway $gateway;

    private SecurityUser $actor;

    protected function setUp(): void
    {
        self::bootKernel();
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        self::assertInstanceOf(ScriptedPlatform::class, $platform);
        $gateway = self::getContainer()->get('test.report_gateway');
        self::assertInstanceOf(ReportGateway::class, $gateway);
        $this->platform = $platform;
        $this->gateway = $gateway;
        $this->actor = UserMother::create();
    }

    public function testThePortResolvesToTheSymfonyAiAdapter(): void
    {
        self::assertInstanceOf(SymfonyAiReportGateway::class, $this->gateway);
    }

    public function testAnOutputThatFitsBecomesAReport(): void
    {
        // Arrange
        $this->platform->script(static fn (): ObjectResult => new ObjectResult(['summary' => 'Ok.', 'sections' => [['title' => 'A', 'content' => 'x']]]));

        // Act
        $report = $this->gateway->generate($this->actor, 'Report.');

        // Assert
        self::assertInstanceOf(Report::class, $report);
        self::assertSame('Ok.', $report->summary());
    }

    public function testAnOutputThatBreaksAConstraintIsRejectedByTheValidatorOfTheContainer(): void
    {
        // Arrange: no sections, and the DTO asks for at least one.
        $this->platform->script(static fn (): ObjectResult => new ObjectResult(['summary' => 'Nothing.', 'sections' => []]));

        // Act
        try {
            $this->gateway->generate($this->actor, 'Report.');
            self::fail('The constraint of the DTO must be checked by the validator of the container.');
        } catch (AgentUnavailableException $exception) {
            // Assert
            self::assertInstanceOf(ValidationException::class, $exception->getPrevious());
        }
    }
}
