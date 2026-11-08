<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Auth;

use App\Application\Security\UserProvider;
use App\Domain\Security\Exceptions\InvalidTokenException;
use App\Domain\Security\Exceptions\RefreshTokenNotFoundException;
use App\Domain\Security\TokenInterface;
use App\Infrastructure\Validator\Auth\RefreshTokenValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class RefreshTokenController
{
    public function __construct(
        private TokenInterface $tokenService,
        private UserProvider $userProvider,
    ) {
    }

    /**
     * @throws ValidatorException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        RefreshTokenValidator::validateRefreshTokenRequest($data);

        try {
            $refreshToken = $data['refresh_token'];

            // Validar el refresh token y obtener el username (esto también lo elimina de la BD)
            $username = $this->tokenService->validateRefreshToken($refreshToken);

            // Cargar el usuario usando el username
            $user = $this->userProvider->loadUserByIdentifier($username);

            // Generar nuevos tokens
            $newJwtToken = $this->tokenService->getJwtToken($user);
            $newRefreshToken = $this->tokenService->getRefreshToken($user);

            return new JsonResponse([
                'data' => [
                    'token' => $newJwtToken,
                    'refresh_token' => $newRefreshToken,
                ],
            ], Response::HTTP_OK);
        } catch (RefreshTokenNotFoundException) {
            return new JsonResponse([
                'error' => 'Invalid refresh token',
                'message' => 'Refresh token not found or already used',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (InvalidTokenException) {
            return new JsonResponse([
                'error' => 'Invalid refresh token',
                'message' => 'Refresh token has expired',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
