<?php

declare(strict_types=1);

namespace App\Tests\Double\Http;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The response factory of every HTTP client the test container builds (framework.yaml, when@test).
 * It is not about the AI: any integration, today or tomorrow, that sends a request through a
 * container client is recorded here and fails loudly, so no test reaches the network by accident.
 * Clients built outside the container do not pass through here (see NoProviderOutsideTheContainerTest).
 *
 * To get controlled responses in a test, build your own client and hand it to the object under test
 * through its constructor; the trap does not touch it:
 *
 *     $client = new MockHttpClient([new MockResponse('{"ok":true}')]);
 *     $integration = new MyIntegration($client);
 *
 * Replacing the container's client does not work: measured with
 * `self::getContainer()->set('http_client', $mock)`, the services that already depend on it keep
 * the trapped one.
 */
final class NoNetworkHttpClientTrap
{
    /** @var list<array{method: string, url: string, authorization: string|null}> */
    private array $requests = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $headers = array_map(static fn (mixed $header): string => implode(',', (array) $header), (array) ($options['normalized_headers'] ?? []));
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'authorization' => $headers['authorization'] ?? null,
        ];

        throw new \LogicException(\sprintf('A test tried to reach %s %s.', $method, $url));
    }

    /**
     * @return list<array{method: string, url: string, authorization: string|null}>
     */
    public function requests(): array
    {
        return $this->requests;
    }
}
