<?php

declare(strict_types=1);

namespace App\Infrastructure\Listener;

use App\Domain\Security\FirewallInterface;
use App\Infrastructure\Security\FirewallException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class RequestListener implements EventSubscriberInterface
{
    public const string LOGGED_USER_ATTRIBUTE = 'logged_user';

    public function __construct(
        private FirewallInterface $firewall,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['firewall', 5],
        ];
    }

    public function firewall(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $user = $this->getUser();

        if (!$this->firewall->isGranted($request, $user)) {
            throw FirewallException::forbidden();
        }

        if ($user) {
            $request->attributes->set(self::LOGGED_USER_ATTRIBUTE, $user);
        }
    }

    private function getUser(): ?UserInterface
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return null;
        }

        if (!\is_object($user = $token->getUser())) {
            return null;
        }

        return $user;
    }
}
