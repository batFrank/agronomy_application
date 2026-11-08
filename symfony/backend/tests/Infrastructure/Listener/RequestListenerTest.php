<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Listener;

use App\Domain\Security\FirewallInterface;
use App\Domain\Security\SecurityException;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Infrastructure\Listener\RequestListener;
use App\Infrastructure\Security\FirewallException;
use App\Tests\Mother\Application\Security\UserMother as SecurityUserMother;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RequestListenerTest extends TestCase
{
    /**
     * @throws Exception
     * @throws FirewallException
     * @throws InvalidEmailException
     */
    public function testFirewallGrantedWithoutUser(): void
    {
        $firewall = $this->createMock(FirewallInterface::class);
        $event = $this->createMock(RequestEvent::class);
        $request = $this->createStub(Request::class);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $firewall->expects($this->once())
            ->method('isGranted')
            ->with($request, null)
            ->willReturn(true);

        $event->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);

        $listener = new RequestListener($firewall, $tokenStorage);
        $listener->firewall($event);
    }

    /**
     * @throws Exception
     * @throws InvalidEmailException
     */
    public function testFirewallThrowsExceptionWhenAccessDenied(): void
    {
        $firewall = $this->createMock(FirewallInterface::class);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $event = $this->createMock(RequestEvent::class);
        $request = $this->createStub(Request::class);

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $firewall->expects($this->once())
            ->method('isGranted')
            ->with($request, null)
            ->willReturn(false);

        $event->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);

        $listener = new RequestListener($firewall, $tokenStorage);
        $this->expectException(FirewallException::class);
        $this->expectExceptionMessage('Forbidden');
        $listener->firewall($event);
    }

    /**
     * @throws Exception
     * @throws \ReflectionException
     */
    public function testGetUserReturnsNullIfNoToken(): void
    {
        $firewall = $this->createStub(FirewallInterface::class);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn(null);

        $listener = new RequestListener($firewall, $tokenStorage);

        // Use reflection to access the private method
        $reflectionClass = new \ReflectionClass(RequestListener::class);
        $method = $reflectionClass->getMethod('getUser');

        $this->assertNull($method->invoke($listener));
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertEquals(
            [
                KernelEvents::REQUEST => ['firewall', 5],
            ],
            RequestListener::getSubscribedEvents()
        );
    }

    /**
     * @throws Exception
     * @throws FirewallException
     * @throws InvalidEmailException
     * @throws InvalidUuidException
     * @throws SecurityException
     */
    public function testFirewallGrantedWithUser(): void
    {
        $user = SecurityUserMother::create();

        $token = $this->createMock(TokenInterface::class);
        $token
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        $firewall = $this->createMock(FirewallInterface::class);
        $firewall
            ->expects($this->once())
            ->method('isGranted')
            ->with($this->anything(), $user)
            ->willReturn(true);

        $request = $this->createStub(Request::class);
        $attributes = $this->createMock(ParameterBag::class);
        $attributes
            ->expects($this->once())
            ->method('set')
            ->with(RequestListener::LOGGED_USER_ATTRIBUTE, $user);
        $request->attributes = $attributes;

        $event = $this->createMock(RequestEvent::class);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);

        $listener = new RequestListener($firewall, $tokenStorage);

        $listener->firewall($event);
    }
}
