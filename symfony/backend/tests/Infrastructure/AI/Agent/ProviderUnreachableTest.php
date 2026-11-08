<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Agent;

use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Double\Http\NoNetworkHttpClientTrap;
use App\Tests\Infrastructure\AI\Agent\Double\ScriptedPlatform;
use App\Tests\Infrastructure\AI\Agent\UserDirectory\UserDirectoryScenarioTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What these tests guarantee: every HTTP client of the test container answers through NoNetworkHttpClientTrap,
 * which records the request and fails; the credential is forced by phpunit.dist.xml; and the
 * platform double exposes nothing but its contract.
 *
 * What they do NOT guarantee: a transport built OUTSIDE the container does not go through the trap.
 * The measured example is the SDK platform factory called without a client, which ends in the
 * default HTTP client factory. NoProviderOutsideTheContainerTest holds that line by failing if the
 * project code ever builds one; it is not a network barrier.
 */
class ProviderUnreachableTest extends KernelTestCase
{
    use UserDirectoryScenarioTrait;

    private const string TEST_KEY = 'test-key-not-used';

    /**
     * @return \Generator<string, array{string}>
     */
    public static function providerVariableProvider(): \Generator
    {
        yield 'api key' => ['OPENROUTER_API_KEY'];
        yield 'model' => ['OPENROUTER_MODEL'];
    }

    #[DataProvider('providerVariableProvider')]
    public function testProviderVariablesAreTheForcedTestOnesWhateverTheShellExports(string $variable): void
    {
        $expected = 'OPENROUTER_API_KEY' === $variable ? self::TEST_KEY : 'test/scripted-model';

        self::assertSame($expected, $_SERVER[$variable] ?? null);
        self::assertSame($expected, $_ENV[$variable] ?? null);
        self::assertSame($expected, getenv($variable));
    }

    public function testPlatformDoubleExposesNothingButItsContract(): void
    {
        $class = new \ReflectionClass(ScriptedPlatform::class);

        self::assertSame([], $class->getProperties(\ReflectionProperty::IS_PUBLIC));
        self::assertSame(
            ['__construct', 'getModelCatalog', 'inputs', 'invoke', 'script'],
            $this->sorted(array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $class->getMethods(\ReflectionMethod::IS_PUBLIC),
            )),
        );
    }

    /**
     * The canary: a request that leaves through the container's HTTP client, with the credential
     * the tests carry, ends in the trap and not at the provider.
     */
    public function testARequestToTheProviderEndsInTheTrap(): void
    {
        self::bootKernel();
        $client = self::getContainer()->get('test.http_client');
        $trap = self::getContainer()->get(NoNetworkHttpClientTrap::class);
        Assert::assertInstanceOf(HttpClientInterface::class, $client);
        Assert::assertInstanceOf(NoNetworkHttpClientTrap::class, $trap);

        try {
            $client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', ['auth_bearer' => $_SERVER['OPENROUTER_API_KEY']])->getStatusCode();
            self::fail('The request must not leave the test container.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('openrouter.ai', $exception->getMessage());
        }

        self::assertSame(
            [['method' => 'POST', 'url' => 'https://openrouter.ai/api/v1/chat/completions', 'authorization' => 'Authorization: Bearer ' . self::TEST_KEY]],
            $trap->requests(),
        );
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidEmailException
     */
    public function testTheWholeAgentLoopMakesNoHttpRequest(): void
    {
        // Arrange
        self::bootKernel();
        $this->givenTheDirectory();
        $this->givenTheActorIs($this->admin);
        $platform = self::getContainer()->get('test.ai.scripted_platform');
        $agent = self::getContainer()->get('test.ai.agent.user_directory');
        $trap = self::getContainer()->get(NoNetworkHttpClientTrap::class);
        Assert::assertInstanceOf(ScriptedPlatform::class, $platform);
        Assert::assertInstanceOf(AgentInterface::class, $agent);
        Assert::assertInstanceOf(NoNetworkHttpClientTrap::class, $trap);
        $platform->script(static fn (array|string|object $input) => $input instanceof MessageBag && $input->count() > 2
            ? 'Done.'
            : new ToolCallResult([new ToolCall('call-1', 'list_users', [])]));

        // Act
        $answer = $agent->call('Who is there?')->getContent();
        $this->leaveTheContext();

        // Assert
        self::assertSame('Done.', $answer);
        self::assertSame([], $trap->requests());
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
