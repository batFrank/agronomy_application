<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Domain\Security\ApiKeyAuthenticationException;
use App\Domain\Security\ApiKeyAuthenticationServiceInterface;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Infrastructure\Security\Firewall;
use App\Infrastructure\Service\RouteMethodChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Yaml\Yaml;

class FirewallTest extends TestCase
{
    /**
     * @throws Exception
     */
    private function getFirewall(
        ?RouteMethodChecker $checker = null,
        ?ApiKeyAuthenticationServiceInterface $apiKeyAuthenticationService = null,
    ): Firewall {
        return new Firewall(
            [
                'prefix' => '/v1',
                'access_control' => [
                    [
                        'path' => '^/health-check',
                        'roles' => 'PUBLIC_ACCESS',
                    ],
                    [
                        'path' => '^/user-public',
                        'roles' => 'PUBLIC_ACCESS',
                    ],
                    [
                        'path' => '^/only-options',
                        'roles' => 'IS_AUTHENTICATED_FULLY',
                    ],
                    [
                        'path' => '^/test-forbidden',
                        'roles' => 'IS_AUTHENTICATED_FULLY',
                    ],
                    [
                        'path' => '^/external',
                        'roles' => 'X_API_KEY',
                    ],
                    [
                        'path' => '^/external-array',
                        'roles' => ['X_API_KEY'],
                    ],
                    [
                        'path' => '^/mixed-auth',
                        'roles' => ['ROLE_ADMIN', 'X_API_KEY'],
                    ],
                ],
            ],
            $checker ?? $this->createStub(RouteMethodChecker::class),
            $apiKeyAuthenticationService ?? $this->createStub(ApiKeyAuthenticationServiceInterface::class),
        );
    }

    /**
     * @param array<string, array<string>> $roleHierarchy
     *
     * @throws Exception
     */
    private function getFirewallWithOverlappingRules(
        ?RouteMethodChecker $checker = null,
        array $roleHierarchy = [],
    ): Firewall {
        return new Firewall(
            security: [
                'prefix' => '/v1',
                'access_control' => [
                    // Specific rule that allows ROLE_CREATOR
                    [
                        'path' => '^/admin/courses/[\w-]+/creators/[\w-]+',
                        'roles' => ['ROLE_ADMIN', 'ROLE_CREATOR'],
                    ],
                    [
                        'path' => '^/admin/test/role-hierarchy',
                        'roles' => ['ROLE_CREATOR'],
                    ],
                    // General rule that would deny ROLE_CREATOR
                    [
                        'path' => '^/admin',
                        'roles' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
                    ],
                ],
            ],
            routeChecker: $checker ?? $this->createStub(RouteMethodChecker::class),
            apiKeyAuthenticationService: $this->createStub(ApiKeyAuthenticationServiceInterface::class),
            roleHierarchy: $roleHierarchy,
        );
    }

    /**
     * Reproduces the real `config/app/security.yaml` ordering for the rules that declare
     * `methods`: a narrow rule first, the broad rule it narrows below it.
     *
     * @throws Exception
     */
    private function getFirewallWithMethodRules(): Firewall
    {
        return new Firewall(
            security: [
                'prefix' => '/v1',
                'access_control' => self::methodRuleFixture(),
            ],
            routeChecker: $this->createStub(RouteMethodChecker::class),
            apiKeyAuthenticationService: $this->createStub(ApiKeyAuthenticationServiceInterface::class),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function methodRuleFixture(): array
    {
        return [
            [
                'path' => '^/admin/users',
                'roles' => ['ROLE_ADMIN', 'ROLE_MANAGER'],
                'methods' => ['GET'],
            ],
            [
                'path' => '^/admin',
                'roles' => ['ROLE_ADMIN'],
            ],
            [
                'path' => '^/auth/otp/login-2fa/generate',
                'roles' => ['X_API_KEY'],
                'methods' => ['POST'],
            ],
            [
                'path' => '^/auth/otp/login-2fa/validate',
                'roles' => ['X_API_KEY'],
                'methods' => ['POST'],
            ],
            [
                'path' => '^/no-methods-declared',
                'roles' => ['ROLE_MANAGER'],
            ],
            [
                'path' => '^/',
                'roles' => 'IS_AUTHENTICATED_FULLY',
            ],
        ];
    }

    /**
     * @throws Exception
     */
    private function userWithRoles(string ...$roles): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getRoles')->willReturn($roles);

        return $user;
    }

    /**
     * A rule's `methods` narrows it. A request whose method is not listed does not match the
     * rule at all, so evaluation continues to the rules below it rather than stopping there.
     *
     * @param array<string> $roles
     *
     * @throws Exception
     * @throws InfrastructureException
     */
    #[DataProvider('provideMethodRestrictedRuleCases')]
    public function testMethodRestrictedRuleAppliesOnlyToItsMethods(
        string $method,
        string $uri,
        array $roles,
        bool $expected,
    ): void {
        $firewall = $this->getFirewallWithMethodRules();

        $request = new Request(server: [
            'REQUEST_URI' => $uri,
            'REQUEST_METHOD' => $method,
        ]);

        self::assertSame($expected, $firewall->isGranted($request, $this->userWithRoles(...$roles)));
    }

    /**
     * @return iterable<string, array{string, string, array<string>, bool}>
     */
    public static function provideMethodRestrictedRuleCases(): iterable
    {
        // The defect this fixes: the GET-only rule matched every method, so the ROLE_ADMIN
        // rule below it was never reached and a manager could create users.
        yield 'manager cannot POST a rule restricted to GET' => ['POST', '/v1/admin/users', ['ROLE_MANAGER'], false];
        yield 'manager cannot PATCH it either' => ['PATCH', '/v1/admin/users/1', ['ROLE_MANAGER'], false];

        // The access the narrow rule exists to grant is unaffected.
        yield 'manager can GET it' => ['GET', '/v1/admin/users', ['ROLE_MANAGER'], true];

        // Falling through reaches the broad rule, which an admin satisfies.
        yield 'admin can POST, via the rule below' => ['POST', '/v1/admin/users', ['ROLE_ADMIN'], true];
        yield 'admin can GET, via the narrow rule' => ['GET', '/v1/admin/users', ['ROLE_ADMIN'], true];

        // A rule with no `methods` key keeps the behaviour it had before the check existed.
        yield 'a rule declaring no methods applies to GET' => ['GET', '/v1/no-methods-declared', ['ROLE_MANAGER'], true];
        yield 'a rule declaring no methods applies to DELETE' => ['DELETE', '/v1/no-methods-declared', ['ROLE_MANAGER'], true];

        // The comparison is case-insensitive on the request's method.
        yield 'a lowercase request method still matches' => ['get', '/v1/admin/users', ['ROLE_MANAGER'], true];
    }

    /**
     * The API-key rules carry `methods` too. A request whose method does not match must not
     * consult the API key at all — it is not that rule's request.
     *
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testMethodRestrictedApiKeyRuleIsSkippedForAnotherMethod(): void
    {
        $apiKeyAuthenticationService = $this->createMock(ApiKeyAuthenticationServiceInterface::class);
        $apiKeyAuthenticationService->expects($this->never())->method('authenticate');

        $firewall = new Firewall(
            security: [
                'prefix' => '/v1',
                'access_control' => [
                    [
                        'path' => '^/auth/otp/login-2fa/generate',
                        'roles' => ['X_API_KEY'],
                        'methods' => ['POST'],
                    ],
                    [
                        'path' => '^/',
                        'roles' => 'IS_AUTHENTICATED_FULLY',
                    ],
                ],
            ],
            routeChecker: $this->createStub(RouteMethodChecker::class),
            apiKeyAuthenticationService: $apiKeyAuthenticationService,
        );

        $request = new Request(server: [
            'REQUEST_URI' => '/v1/auth/otp/login-2fa/generate',
            'REQUEST_METHOD' => 'GET',
        ]);

        // Falls through to `^/`, which an authenticated user satisfies.
        self::assertTrue($firewall->isGranted($request, $this->userWithRoles('ROLE_USER')));
    }

    /**
     * Guards the fixture above against the real config growing a method-restricted rule that
     * no case exercises.
     */
    public function testEveryMethodRestrictedRuleInTheRealConfigIsCovered(): void
    {
        $realConfig = Yaml::parseFile(__DIR__ . '/../../../config/app/security.yaml');

        self::assertSame(
            self::methodRestrictedPaths($realConfig['parameters']['app.security_config']['access_control'] ?? []),
            self::methodRestrictedPaths(self::methodRuleFixture()),
            'a rule in config/app/security.yaml declares methods but the fixture above does not reproduce it, so nothing here exercises it',
        );
    }

    /**
     * The paths of the rules that narrow themselves by method, sorted.
     *
     * @param array<int, array<string, mixed>> $rules
     *
     * @return array<int, string>
     */
    private static function methodRestrictedPaths(array $rules): array
    {
        $paths = [];
        foreach ($rules as $rule) {
            if (!empty($rule['methods'])) {
                $paths[] = (string) $rule['path'];
            }
        }
        sort($paths);

        return $paths;
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testApiKeyEndpointGrantedWithoutUserWhenApiKeyIsValid(): void
    {
        $apiKeyAuthenticationService = $this->createMock(ApiKeyAuthenticationServiceInterface::class);
        $apiKeyAuthenticationService
            ->expects($this->once())
            ->method('authenticate')
            ->with('valid-key');

        $firewall = $this->getFirewall(apiKeyAuthenticationService: $apiKeyAuthenticationService);

        $request = new Request(server: [
            'REQUEST_URI' => '/v1/external/resource',
            'HTTP_X_API_KEY' => 'valid-key',
        ]);

        $this->assertTrue($firewall->isGranted($request));
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    #[DataProvider('provideApiKeyAuthenticationExceptions')]
    public function testApiKeyEndpointBubblesApiKeyAuthenticationException(
        ?string $apiKey,
        ApiKeyAuthenticationException $expectedException,
    ): void {
        $apiKeyAuthenticationService = $this->createMock(ApiKeyAuthenticationServiceInterface::class);
        $apiKeyAuthenticationService
            ->expects($this->once())
            ->method('authenticate')
            ->with($apiKey)
            ->willThrowException($expectedException);

        $firewall = $this->getFirewall(apiKeyAuthenticationService: $apiKeyAuthenticationService);

        $server = [
            'REQUEST_URI' => '/v1/external/resource',
        ];

        if (null !== $apiKey) {
            $server['HTTP_X_API_KEY'] = $apiKey;
        }

        $request = new Request(server: $server);

        $this->expectExceptionObject($expectedException);
        $firewall->isGranted($request);
    }

    public static function provideApiKeyAuthenticationExceptions(): \Generator
    {
        yield 'missing api key' => [
            'apiKey' => null,
            'expectedException' => ApiKeyAuthenticationException::missingApiKey(),
        ];

        yield 'blank api key' => [
            'apiKey' => '   ',
            'expectedException' => ApiKeyAuthenticationException::missingApiKey(),
        ];

        yield 'invalid api key' => [
            'apiKey' => 'invalid-key',
            'expectedException' => ApiKeyAuthenticationException::invalidApiKey(),
        ];

        yield 'inactive api key' => [
            'apiKey' => 'inactive-key',
            'expectedException' => ApiKeyAuthenticationException::inactiveApiKey(),
        ];
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testApiKeyEndpointDoesNotRequireAuthenticatedUser(): void
    {
        $apiKeyAuthenticationService = $this->createMock(ApiKeyAuthenticationServiceInterface::class);
        $apiKeyAuthenticationService
            ->expects($this->once())
            ->method('authenticate')
            ->with('valid-key');

        $firewall = $this->getFirewall(apiKeyAuthenticationService: $apiKeyAuthenticationService);

        $request = new Request(server: [
            'REQUEST_URI' => '/v1/external-array/resource',
            'HTTP_X_API_KEY' => 'valid-key',
        ]);

        $this->assertTrue($firewall->isGranted($request, null));
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testApiKeyEndpointDoesNotAllowJwtUserWithoutApiKey(): void
    {
        $apiKeyAuthenticationService = $this->createMock(ApiKeyAuthenticationServiceInterface::class);
        $apiKeyAuthenticationService
            ->expects($this->once())
            ->method('authenticate')
            ->with(null)
            ->willThrowException(ApiKeyAuthenticationException::missingApiKey());

        $firewall = $this->getFirewall(apiKeyAuthenticationService: $apiKeyAuthenticationService);

        $user = $this->createStub(UserInterface::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN']);

        $request = new Request(server: [
            'REQUEST_URI' => '/v1/external/resource',
        ]);

        $this->expectExceptionObject(ApiKeyAuthenticationException::missingApiKey());
        $firewall->isGranted($request, $user);
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testMixedApiKeyAndRoleRuleDoesNotActivateApiKeyMode(): void
    {
        $apiKeyAuthenticationService = $this->createMock(ApiKeyAuthenticationServiceInterface::class);
        $apiKeyAuthenticationService
            ->expects($this->never())
            ->method('authenticate');

        $firewall = $this->getFirewall(apiKeyAuthenticationService: $apiKeyAuthenticationService);

        $user = $this->createStub(UserInterface::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN']);

        $request = new Request(server: [
            'REQUEST_URI' => '/v1/mixed-auth/resource',
        ]);

        $this->assertTrue($firewall->isGranted($request, $user));
    }

    /**
     * @throws Exception
     */
    public function testPublicEndpoint(): void
    {
        $firewall = $this->getFirewall();

        $this->assertSame(
            true,
            $firewall->isGranted(
                new Request([], [], [], [], [], [
                    'REQUEST_URI' => '/v1/health-check',
                ], null),
            )
        );

        $this->assertSame(
            true,
            $firewall->isGranted(
                new Request([], [], [], [], [], [
                    'REQUEST_URI' => '/v1/user-public/1/users',
                ], null),
            )
        );
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testNoTokenAndNoPublicEndpoint(): void
    {
        $request = new Request(
            server: [
                'REQUEST_URI' => '/v1/test-forbidden',
            ]
        );

        $this->expectExceptionObject(new UnauthorizedHttpException('Unauthorized'));
        $this->getFirewall()->isGranted($request);
    }

    /**
     * Test that overlapping rules work correctly - access is granted if ANY rule allows it.
     *
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testOverlappingRulesAllowAccess(): void
    {
        $firewall = $this->getFirewallWithOverlappingRules();

        // Mock user with ROLE_CREATOR
        $user = $this->createStub(UserInterface::class);
        $user->method('getRoles')->willReturn(['ROLE_USER', 'ROLE_CREATOR']);

        // Request to specific endpoint that should be allowed by the second rule
        $request = new Request(
            server: [
                'REQUEST_URI' => '/v1/admin/courses/123/creators/456',
            ]
        );

        // Should be granted because the specific rule allows ROLE_CREATOR
        // even though the general rule would deny it
        $result = $firewall->isGranted($request, $user);
        $this->assertTrue($result);
    }

    /**
     * Test that overlapping rules deny access when no rule allows it.
     *
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testOverlappingRulesDenyAccess(): void
    {
        $firewall = $this->getFirewallWithOverlappingRules();

        // Mock user with ROLE_USER only (not allowed by any rule)
        $user = $this->createStub(UserInterface::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        // Request to specific endpoint
        $request = new Request(
            server: [
                'REQUEST_URI' => '/v1/admin/courses/123/creators/456',
            ]
        );

        // Should be denied because no rule allows ROLE_USER
        $result = $firewall->isGranted($request, $user);
        $this->assertFalse($result);
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testOptionsRequest(): void
    {
        $checker = $this->createStub(RouteMethodChecker::class);
        $checker
            ->method('isOptionsOnlyRoute')
            ->willReturnCallback(fn (string $path) => '/v1/only-options' === $path);

        $firewall = $this->getFirewall($checker);

        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/v1/test-forbidden',
            'REQUEST_METHOD' => 'OPTIONS',
        ], null);

        $result = $firewall->isGranted($request);
        $this->assertSame(true, $result);

        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/v1/only-options',
            'REQUEST_METHOD' => 'OPTIONS',
        ], null);

        $this->expectExceptionObject(new RouteNotFoundException('No route found for "OPTIONS /v1/only-options"'));
        $firewall->isGranted($request);
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testOptionsRequestWithNoPrefix(): void
    {
        $checker = $this->createStub(RouteMethodChecker::class);
        $checker
            ->method('isOptionsOnlyRoute')
            ->willReturnCallback(fn (string $path) => '/v2/only-options' === $path);

        $firewall = $this->getFirewall($checker);

        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/v2/test-forbidden',
            'REQUEST_METHOD' => 'OPTIONS',
        ], null);

        $result = $firewall->isGranted($request);
        $this->assertSame(true, $result);
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testOptionsRequestWithNoPrefixAndNoOptionsOnlyRoute(): void
    {
        $checker = $this->createStub(RouteMethodChecker::class);
        $checker
            ->method('isOptionsOnlyRoute')
            ->willReturnCallback(fn (string $path) => false);

        $firewall = $this->getFirewall($checker);

        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/v2/test-forbidden',
            'REQUEST_METHOD' => 'OPTIONS',
        ], null);

        $result = $firewall->isGranted($request);
        $this->assertSame(true, $result);
    }

    /**
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testNoPrefix(): void
    {
        $firewall = $this->getFirewall();

        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/v2/test-forbidden',
        ], null);

        $result = $firewall->isGranted($request);
        $this->assertSame(true, $result);
    }

    /**
     * @param array<string> $userRoles
     *
     * @throws Exception
     * @throws InfrastructureException
     */
    #[DataProvider('provideTestRoleHierarchy')]
    public function testRoleHierarchy(array $userRoles, bool $isGranted): void
    {
        $firewall = $this->getFirewallWithOverlappingRules(
            roleHierarchy: [
                'ROLE_SUPER_ADMIN' => [
                    'ROLE_ADMIN',
                ],
                'ROLE_ADMIN' => [
                    'ROLE_CREATOR',
                    'ROLE_USER',
                ],
                'ROLE_CREATOR' => [
                    'ROLE_USER',
                ],
            ]
        );

        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/v1/admin/test/role-hierarchy',
        ], null);

        $user = $this->createStub(UserInterface::class);
        $user->method('getRoles')
            ->willReturn($userRoles);
        $this->assertSame($isGranted, $firewall->isGranted($request, $user));
    }

    public static function provideTestRoleHierarchy(): \Generator
    {
        yield 'role creator' => [
            'userRoles' => ['ROLE_CREATOR'],
            'isGranted' => true,
        ];

        yield 'role user' => [
            'userRoles' => ['ROLE_USER'],
            'isGranted' => false,
        ];

        yield 'role admin' => [
            'userRoles' => ['ROLE_ADMIN'],
            'isGranted' => true,
        ];

        yield 'role superadmin' => [
            'userRoles' => ['ROLE_SUPER_ADMIN'],
            'isGranted' => true,
        ];
    }

    /**
     * Test that /user/profile endpoint is accessible to any authenticated user.
     *
     * @throws Exception
     * @throws InfrastructureException
     */
    public function testUserProfileEndpointAccessibleToAuthenticatedUsers(): void
    {
        // Create firewall with the actual security configuration
        $firewall = new Firewall(
            [
                'prefix' => '/api/v1',
                'access_control' => [
                    ['path' => '^/health-check', 'roles' => 'PUBLIC_ACCESS'],
                    ['path' => '^/auth/login', 'roles' => 'PUBLIC_ACCESS'],
                    ['path' => '^/auth/refresh', 'roles' => 'PUBLIC_ACCESS'],
                    ['path' => '^/', 'roles' => 'IS_AUTHENTICATED_FULLY'],
                ],
            ],
            $this->createStub(RouteMethodChecker::class),
            $this->createStub(ApiKeyAuthenticationServiceInterface::class),
        );

        // Test with ROLE_USER - should be granted
        $userWithRoleUser = $this->createStub(UserInterface::class);
        $userWithRoleUser->method('getRoles')->willReturn(['ROLE_USER']);

        $request = new Request([], [], [], [], [], [
            'REQUEST_URI' => '/api/v1/user/profile',
        ]);

        $result = $firewall->isGranted($request, $userWithRoleUser);
        $this->assertTrue($result, 'User with ROLE_USER should be able to access /user/profile');

        // Test with ROLE_ADMIN - should also be granted
        $userWithRoleAdmin = $this->createStub(UserInterface::class);
        $userWithRoleAdmin->method('getRoles')->willReturn(['ROLE_ADMIN']);

        $result = $firewall->isGranted($request, $userWithRoleAdmin);
        $this->assertTrue($result, 'User with ROLE_ADMIN should be able to access /user/profile');

        // Test without authentication - should be denied
        $this->expectExceptionObject(new UnauthorizedHttpException('Unauthorized'));
        $result = $firewall->isGranted($request, null);
        $this->assertFalse($result, 'Unauthenticated user should not be able to access /user/profile');
    }
}
