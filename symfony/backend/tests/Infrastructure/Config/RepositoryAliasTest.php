<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Config;

use App\Domain\ApiKey\ApiKeyRepository;
use App\Domain\OTP\OtpRepository;
use App\Domain\Scheduler\ScheduledTaskRepository;
use App\Domain\Security\RefreshTokenRepository;
use App\Domain\Translation\TranslationRepository;
use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\ApiKey\DBALApiKeyRepository;
use App\Infrastructure\Persistence\ApiKey\InMemoryApiKeyRepository;
use App\Infrastructure\Persistence\OTP\DBALOtpRepository;
use App\Infrastructure\Persistence\OTP\InMemoryOtpRepository;
use App\Infrastructure\Persistence\Scheduler\DBALScheduledTaskRepository;
use App\Infrastructure\Persistence\Scheduler\InMemoryScheduledTaskRepository;
use App\Infrastructure\Persistence\Security\DBALRefreshTokenRepository;
use App\Infrastructure\Persistence\Security\InMemoryRefreshTokenRepository;
use App\Infrastructure\Persistence\Translation\DBALTranslationRepository;
use App\Infrastructure\Persistence\User\DBALUserRepository;
use App\Infrastructure\Persistence\User\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

#[CoversNothing]
class RepositoryAliasTest extends TestCase
{
    private const string CONFIG_DIR = __DIR__ . '/../../../config';

    /**
     * @var array<string, ContainerBuilder>
     */
    private static array $containers = [];

    /**
     * @throws \Exception
     */
    #[DataProvider('provideRepositoryResolvesToItsExpectedImplementationCases')]
    public function testRepositoryResolvesToItsExpectedImplementation(
        string $interface,
        string $expectedOutsideTests,
        string $expectedInTests,
    ): void {
        self::assertSame(
            $expectedOutsideTests,
            self::resolve($interface, withTestOverrides: false),
            \sprintf('"%s" resolves to the wrong implementation in the dev/prod container.', $interface),
        );

        self::assertSame(
            $expectedInTests,
            self::resolve($interface, withTestOverrides: true),
            \sprintf('"%s" resolves to the wrong implementation in the test container.', $interface),
        );
    }

    /**
     * Guards the map above against a new repository being added without an entry here.
     */
    public function testEveryDomainRepositoryIsCovered(): void
    {
        $files = glob(__DIR__ . '/../../../src/Domain/*/*Repository.php');
        self::assertIsArray($files);

        $interfaces = [];
        foreach ($files as $file) {
            $relative = substr($file, (int) strpos($file, 'src/Domain/') + \strlen('src/Domain/'), -4);
            $interfaces[] = 'App\Domain\\' . str_replace('/', '\\', $relative);
        }
        sort($interfaces);

        $covered = [];
        foreach (self::provideRepositoryResolvesToItsExpectedImplementationCases() as [$interface]) {
            $covered[] = $interface;
        }
        sort($covered);

        self::assertSame($interfaces, $covered);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideRepositoryResolvesToItsExpectedImplementationCases(): iterable
    {
        yield 'ApiKey' => [ApiKeyRepository::class, DBALApiKeyRepository::class, InMemoryApiKeyRepository::class];
        yield 'Otp' => [OtpRepository::class, DBALOtpRepository::class, InMemoryOtpRepository::class];
        yield 'RefreshToken' => [RefreshTokenRepository::class, DBALRefreshTokenRepository::class, InMemoryRefreshTokenRepository::class];
        yield 'ScheduledTask' => [ScheduledTaskRepository::class, DBALScheduledTaskRepository::class, InMemoryScheduledTaskRepository::class];
        yield 'User' => [UserRepository::class, DBALUserRepository::class, InMemoryUserRepository::class];
        // Deliberate: InMemoryTranslationRepository exists but services_test.yaml does not
        // override the DBAL one, so translations hit the database even in tests.
        yield 'Translation' => [TranslationRepository::class, DBALTranslationRepository::class, DBALTranslationRepository::class];
    }

    /**
     * @throws \Exception
     */
    private static function resolve(string $interface, bool $withTestOverrides): string
    {
        $container = self::container($withTestOverrides);

        if ($container->hasAlias($interface)) {
            return (string) $container->getAlias($interface);
        }

        return $container->hasDefinition($interface) ? $interface : 'nothing';
    }

    /**
     * @throws \Exception
     */
    private static function container(bool $withTestOverrides): ContainerBuilder
    {
        $key = $withTestOverrides ? 'test' : 'production';

        if (!isset(self::$containers[$key])) {
            $container = new ContainerBuilder();
            $loader = new YamlFileLoader($container, new FileLocator(self::CONFIG_DIR));
            $loader->load('services.yaml');

            if ($withTestOverrides) {
                $loader->load('services_test.yaml');
            }

            self::$containers[$key] = $container;
        }

        return self::$containers[$key];
    }
}
