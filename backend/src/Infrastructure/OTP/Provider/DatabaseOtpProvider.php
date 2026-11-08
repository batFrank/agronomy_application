<?php

declare(strict_types=1);

namespace App\Infrastructure\OTP\Provider;

use App\Domain\OTP\DeliveryChannel\DeliveryChannelDispatcher;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelValue;
use App\Domain\OTP\Exception\OtpNotFoundException;
use App\Domain\OTP\Otp;
use App\Domain\OTP\OtpCriteria;
use App\Domain\OTP\OtpRepository;
use App\Domain\OTP\Provider\OtpProviderException;
use App\Domain\OTP\Provider\OtpProviderInterface;
use App\Domain\OTP\Provider\Request;
use App\Domain\OTP\Provider\Verification;
use App\Domain\OTP\Provider\VerificationResult;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Criteria\Condition\Condition;
use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Uuid\UuidGenerator;
use Random\Randomizer;

final readonly class DatabaseOtpProvider implements OtpProviderInterface
{
    public function __construct(
        private OtpRepository $otpRepository,
        private UuidGenerator $uuidGenerator,
        private DeliveryChannelDispatcher $deliveryChannelDispatcher,
        #[\SensitiveParameter]
        private string $secret,
    ) {
    }

    #[\Override]
    public function generate(Request $request): void
    {
        $this->invalidate($request->getResource(), $request->getPurpose()); // Invalidate all previous codes

        if (null === $request->getDeliveryChannel()) {
            throw OtpProviderException::deliveryChannelCannotBeNull();
        }

        $code = $this->generateStringCode($request->getCodeLength());
        $hashed = hash_hmac(algo: 'sha256', data: $code, key: $this->secret);

        $createdAt = new \DateTimeImmutable();
        $otp = new Otp(
            id: $this->uuidGenerator->generate(),
            resource: $request->getResource(),
            purpose: $request->getPurpose(),
            codeSecret: $hashed,
            attempts: 0,
            maxAttempts: 1,
            revoked: false,
            expiresAt: $createdAt->modify('+' . $request->getTtl() . ' seconds'),
            consumedAt: null,
            createdAt: $createdAt,
        );

        $this->otpRepository->put($otp);

        $this->deliveryChannelDispatcher->dispatch(new DeliveryChannelValue(
            destination: $request->getDeliveryChannel()->getDestination(),
            type: $request->getDeliveryChannel()->getType(),
            value: $code,
        ));
    }

    #[\Override]
    public function verify(Verification $verification): VerificationResult
    {
        try {
            $otp = $this->otpRepository->findOneBy(
                OtpCriteria::createEmpty()
                    ->filterByResource($verification->getResource())
                    ->filterByPurpose($verification->getPurpose())
                    ->filterByRevoked(false)
                    ->filterByConsumedAt(value: null, condition: Condition::IsNull)
            );
        } catch (OtpNotFoundException) {
            return VerificationResult::NotFound;
        }

        // If is revoked, early exit
        if ($otp->isRevoked()) {
            return VerificationResult::InvalidCode;
        }

        if ($otp->getAttempts() >= $otp->getMaxAttempts()) {
            return VerificationResult::MaxAttemptsReached; // It was used
        }

        // Mark as used. It does not matter if it is valid or not. To avoid brute force attack.
        $toUpdate = $this->buildOtpForUpdate(
            otp: $otp,
            consumedAt: new \DateTimeImmutable(),
            increaseAttempts: true,
        );
        $this->otpRepository->put($toUpdate);

        $hashed = hash_hmac(
            algo: 'sha256',
            data: $verification->getCode(),
            key: $this->secret,
        );
        if (!hash_equals($hashed, $otp->getCodeSecret())) {
            return VerificationResult::InvalidCode;
        }

        if ($toUpdate->getConsumedAt() > $otp->getExpiresAt()) {
            return VerificationResult::Expired;
        }

        return VerificationResult::Valid;
    }

    #[\Override]
    public function invalidate(Resource $resource, Purpose $purpose): void
    {
        $codes = $this->otpRepository->findBy(
            OtpCriteria::createEmpty()->filterByResource($resource)
                ->filterByPurpose($purpose)
                ->filterByRevoked(false)
                ->filterByConsumedAt(value: null, condition: Condition::IsNull)
        );

        if ($codes->isEmpty()) {
            return;
        }

        foreach ($codes->all() as $otp) {
            $toUpdate = $this->buildOtpForUpdate(
                otp: $otp,
                revoked: true,
            );

            $this->otpRepository->put($toUpdate);
        }
    }

    private function generateStringCode(int $length = 6): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomizer = new Randomizer();

        return $randomizer->getBytesFromString($characters, $length);
    }

    private function buildOtpForUpdate(
        Otp $otp,
        ?\DateTimeImmutable $consumedAt = null,
        ?bool $revoked = null,
        bool $increaseAttempts = false,
    ): Otp {
        $attempts = $otp->getAttempts();
        if ($increaseAttempts) {
            ++$attempts;
        }

        return new Otp(
            id: $otp->getId(),
            resource: $otp->getResource(),
            purpose: $otp->getPurpose(),
            codeSecret: $otp->getCodeSecret(),
            attempts: $attempts,
            maxAttempts: $otp->getMaxAttempts(),
            revoked: $revoked ?? $otp->isRevoked(),
            expiresAt: $otp->getExpiresAt(),
            consumedAt: $consumedAt ?? $otp->getConsumedAt(),
            createdAt: $otp->getCreatedAt(),
        );
    }
}
