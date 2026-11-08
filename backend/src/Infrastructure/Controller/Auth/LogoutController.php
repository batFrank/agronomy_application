<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Auth;

use App\Domain\Security\Exceptions\RefreshTokenNotFoundException;
use App\Domain\Security\RefreshTokenCriteria;
use App\Domain\Security\RefreshTokenRepository;
use App\Infrastructure\Validator\Auth\RefreshTokenValidator;
use App\Infrastructure\Validator\ValidatorException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class LogoutController
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    /**
     * @throws ValidatorException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        RefreshTokenValidator::validateRefreshTokenRequest($data);

        $refreshTokenValue = $data['refresh_token'];

        try {
            $refreshToken = $this->refreshTokenRepository->findOneBy(
                RefreshTokenCriteria::createEmpty()->filterByToken($refreshTokenValue)
            );

            $this->refreshTokenRepository->delete($refreshToken);

            return new JsonResponse([
                'message' => 'Logout successful',
            ], Response::HTTP_OK);
        } catch (RefreshTokenNotFoundException) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Refresh token not found or already invalidated',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable) {
            return new JsonResponse([
                'error' => true,
                'message' => 'An error occurred while logging out',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
