<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller\Auth\Otp;

use App\Application\Query\GetUser;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelValue;
use App\Domain\OTP\DeliveryChannel\Type;
use App\Domain\OTP\Provider\OtpProviderInterface;
use App\Domain\OTP\Provider\Request as OTPRequest;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Resource\ResourceType;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\User;
use App\Domain\User\UserCriteria;
use App\Infrastructure\Bus\QueryBusAccessor;
use App\Infrastructure\Validator\OTP\GenerateLogin2FAOtpValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller is used to generate the OTP for the login 2FA. No extra validation is done against the user.
 * Because it is in an API KEY context, back to back is the communication, is expected from the caller to already have
 * the email and user verified (Password and Email).
 */
final readonly class GenerateLogin2FAOtpController extends QueryBusAccessor
{
    public function __invoke(Request $request, OtpProviderInterface $otpProvider): Response
    {
        $body = json_decode($request->getContent(), true) ?? [];

        GenerateLogin2FAOtpValidator::validateGenerateLogin2FAOtpRequest($body);

        $email = new Email($body['email']);

        try {
            /** @var User $user */
            $user = $this->ask(new GetUser(
                criteria: UserCriteria::createEmpty()
                    ->filterByEmail($email)
            ));
        } catch (UserNotFoundException) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        $otpProvider->generate(request: new OTPRequest(
            resource: new Resource(
                id: $user->getId(),
                type: ResourceType::User,
            ),
            purpose: Purpose::Login2FA,
            codeLength: 6,
            ttl: 60,
            deliveryChannel: new DeliveryChannelValue(
                destination: $email,
                type: Type::Email,
                value: null,
            )
        ));

        return new Response(
            status: Response::HTTP_NO_CONTENT,
        );
    }
}
