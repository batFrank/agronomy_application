<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Auth\Otp;

use App\Application\Query\GetUser;
use App\Domain\OTP\Provider\OtpProviderInterface;
use App\Domain\OTP\Provider\Verification;
use App\Domain\OTP\Provider\VerificationResult;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Email\InvalidEmailException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Resource\ResourceType;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Infrastructure\Bus\QueryBusAccessor;
use App\Infrastructure\Validator\OTP\ValidateLogin2FAOtpValidator;
use App\Infrastructure\Validator\ValidatorException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ValidateLogin2FAOtpController extends QueryBusAccessor
{
    /**
     * @throws ValidatorException
     * @throws InfrastructureException
     * @throws InvalidEmailException
     */
    public function __invoke(
        Request $request,
        OtpProviderInterface $otpProvider,
        LoggerInterface $logger,
    ): Response {
        $body = json_decode($request->getContent(), true) ?? [];

        ValidateLogin2FAOtpValidator::validateValidateLogin2FAOtpRequest($body);

        $email = new Email($body['email']);
        $code = $body['code'];

        try {
            /** @var User $user */
            $user = $this->ask(new GetUser(
                criteria: UserCriteria::createEmpty()
                    ->filterByEmail($email)
            ));

            $result = $otpProvider->verify(new Verification(
                resource: new Resource(
                    id: $user->getId(),
                    type: ResourceType::User,
                ),
                purpose: Purpose::Login2FA,
                code: $code,
            ));

            if (VerificationResult::Valid === $result) {
                return new Response(status: Response::HTTP_OK);
            }
            $logger->warning('User otp validation failed: ' . $result->name);
        } catch (UserNotFoundException) {
            $logger->warning('User otp validation failed: User not found.');
        }

        return new JsonResponse(
            data: ['message' => 'Unauthorized.'],
            status: Response::HTTP_UNAUTHORIZED
        );
    }
}
