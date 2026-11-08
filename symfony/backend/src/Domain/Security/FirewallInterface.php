<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Exception\UnauthorizedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

interface FirewallInterface
{
    /**
     * @throws InfrastructureException
     * @throws UnauthorizedException
     */
    public function isGranted(Request $request, ?UserInterface $user): bool;
}
