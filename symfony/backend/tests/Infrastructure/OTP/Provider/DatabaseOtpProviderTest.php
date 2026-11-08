<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\OTP\Provider;

use App\Domain\OTP\DeliveryChannel\DeliveryChannelDispatcher;
use App\Domain\OTP\DeliveryChannel\DeliveryChannelValue;
use App\Domain\OTP\DeliveryChannel\Type;
use App\Domain\OTP\Exception\OtpNotFoundException;
use App\Domain\OTP\Otp;
use App\Domain\OTP\OtpCollection;
use App\Domain\OTP\OtpCriteria;
use App\Domain\OTP\OtpRepository;
use App\Domain\OTP\Provider\OtpProviderException;
use App\Domain\OTP\Provider\Request;
use App\Domain\OTP\Provider\Verification;
use App\Domain\OTP\Provider\VerificationResult;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\Condition\Condition;
use App\Domain\Shared\Email\Email;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\UuidGenerator;
use App\Infrastructure\OTP\Provider\DatabaseOtpProvider;
use App\Tests\Mother\Domain\OTP\OtpMother;
use App\Tests\Mother\Domain\Shared\Resource\ResourceMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseOtpProvider::class)]
class DatabaseOtpProviderTest extends TestCase
{
    /**
     * @throws InfrastructureException
     */
    #[DataProvider('provideGenerateWhenNoDeliveryChannelCases')]
    public function testGenerateWhenNoDeliveryChannel(OtpCollection $otpCollection): void
    {
        $otpRepository = $this->createMock(OtpRepository::class);
        $provider = $this->getProvider(
            otpRepository: $otpRepository,
        );

        $resource = ResourceMother::create();

        $otpRepository->expects($this->once())
            ->method('findBy')
            ->with(
                OtpCriteria::createEmpty()
                    ->filterByResource($resource)
                    ->filterByPurpose(Purpose::Login2FA)
                    ->filterByRevoked(false)
                    ->filterByConsumedAt(value: null, condition: Condition::IsNull)
            )->willReturn($otpCollection);

        if ($otpCollection->isEmpty()) {
            $otpRepository->expects($this->never())
                ->method('put');
        } else {
            $otpRepository->expects($this->exactly($otpCollection->count()))
                ->method('put')
                ->willReturnCallback(function (Otp $otp) {
                    $this->assertTrue($otp->isRevoked());
                });
        }

        $this->expectExceptionObject(OtpProviderException::deliveryChannelCannotBeNull());
        $provider->generate(new Request(
            resource: $resource,
            purpose: Purpose::Login2FA,
        ));
    }

    /**
     * @throws InvalidUuidException
     * @throws InfrastructureException
     */
    #[DataProvider('provideGenerateWhenNoDeliveryChannelCases')]
    public function testGenerateSuccessful(OtpCollection $otpCollection): void
    {
        $otpRepository = $this->createMock(OtpRepository::class);
        $uuidGenerator = $this->createMock(UuidGenerator::class);
        $deliveryChannelDispatcher = $this->createMock(DeliveryChannelDispatcher::class);
        $provider = $this->getProvider(
            otpRepository: $otpRepository,
            uuidGenerator: $uuidGenerator,
            deliveryChannelDispatcher: $deliveryChannelDispatcher,
        );

        $resource = ResourceMother::create();

        $otpRepository->expects($this->once())
            ->method('findBy')
            ->with(
                OtpCriteria::createEmpty()
                    ->filterByResource($resource)
                    ->filterByPurpose(Purpose::Login2FA)
                    ->filterByRevoked(false)
                    ->filterByConsumedAt(value: null, condition: Condition::IsNull)
            )->willReturn($otpCollection);

        $id = UuidMother::create();
        $uuidGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($id);

        if ($otpCollection->isEmpty()) {
            $otpRepository->expects($this->once())
                ->method('put')
                ->willReturnCallback(function (Otp $otp) use ($id, $resource) {
                    $this->assertEquals($id, $otp->getId());
                    $this->assertEquals($resource, $otp->getResource());
                    $this->assertEquals(Purpose::Login2FA, $otp->getPurpose());
                    $this->assertEquals(0, $otp->getAttempts());
                    $this->assertEquals(1, $otp->getMaxAttempts());
                    $this->assertFalse($otp->isRevoked());
                    $this->assertNull($otp->getConsumedAt());
                });
        } else {
            $newCodePutCall = $otpCollection->count() + 1;
            $putCalls = $this->exactly($newCodePutCall);
            $otpRepository->expects($putCalls)
                ->method('put')
                ->willReturnCallback(function (Otp $otp) use ($id, $resource, $newCodePutCall, $putCalls) {
                    if ($putCalls->numberOfInvocations() === $newCodePutCall) {
                        $this->assertEquals($id, $otp->getId());
                        $this->assertEquals($resource, $otp->getResource());
                        $this->assertEquals(Purpose::Login2FA, $otp->getPurpose());
                        $this->assertEquals(0, $otp->getAttempts());
                        $this->assertEquals(1, $otp->getMaxAttempts());
                        $this->assertFalse($otp->isRevoked());
                        $this->assertNull($otp->getConsumedAt());
                    } else {
                        $this->assertTrue($otp->isRevoked());
                    }
                });
        }

        $deliveryChannelDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (DeliveryChannelValue $value) {
                $this->assertEquals(new Email('example@test.com'), $value->getDestination());
                $this->assertEquals(Type::Email, $value->getType());
                $this->assertNotNull($value->getValue());
                $this->assertEquals(6, \strlen($value->getValue()));
            });

        $provider->generate(new Request(
            resource: $resource,
            purpose: Purpose::Login2FA,
            codeLength: 6,
            deliveryChannel: new DeliveryChannelValue(
                destination: new Email('example@test.com'),
                type: Type::Email,
                value: null,
            ),
        ));
    }

    /**
     * @throws InfrastructureException
     */
    #[DataProvider('provideGenerateWhenNoDeliveryChannelCases')]
    public function testInvalidate(OtpCollection $otpCollection): void
    {
        $otpRepository = $this->createMock(OtpRepository::class);
        $provider = $this->getProvider(
            otpRepository: $otpRepository,
        );

        $resource = ResourceMother::create();

        $otpRepository->expects($this->once())
            ->method('findBy')
            ->with(
                OtpCriteria::createEmpty()
                    ->filterByResource($resource)
                    ->filterByPurpose(Purpose::Login2FA)
                    ->filterByRevoked(false)
                    ->filterByConsumedAt(value: null, condition: Condition::IsNull)
            )->willReturn($otpCollection);

        if ($otpCollection->isEmpty()) {
            $otpRepository->expects($this->never())
                ->method('put');
        } else {
            $otpRepository->expects($this->exactly($otpCollection->count()))
                ->method('put')
                ->willReturnCallback(function (Otp $otp) {
                    $this->assertTrue($otp->isRevoked());
                });
        }

        $provider->invalidate(
            resource: $resource,
            purpose: Purpose::Login2FA,
        );
    }

    /**
     * @throws CollectionException
     */
    public static function provideGenerateWhenNoDeliveryChannelCases(): \Generator
    {
        yield 'no previous otp codes' => [
            'otpCollection' => new OtpCollection([]),
        ];

        yield 'with previous otp codes' => [
            'otpCollection' => new OtpCollection([
                OtpMother::create(),
                OtpMother::create(),
            ]),
        ];
    }

    /**
     * @throws InfrastructureException
     */
    #[DataProvider('provideVerifyCases')]
    public function testVerify(
        ?Otp $otp,
        bool $putCall,
        VerificationResult $expected
    ): void {
        $otpRepository = $this->createMock(OtpRepository::class);
        $provider = $this->getProvider(
            otpRepository: $otpRepository,
        );

        $resource = ResourceMother::create();

        $findOneBy = $otpRepository->expects($this->once())
            ->method('findOneBy')
            ->with(
                OtpCriteria::createEmpty()
                    ->filterByResource($resource)
                    ->filterByPurpose(Purpose::EmailVerification)
                    ->filterByRevoked(false)
                    ->filterByConsumedAt(value: null, condition: Condition::IsNull)
            );
        if (null === $otp) {
            $findOneBy->willThrowException(new OtpNotFoundException());
        } else {
            $findOneBy->willReturn($otp);
        }

        if ($putCall) {
            $otpRepository->expects($this->once())
                ->method('put')
                ->willReturnCallback(function (Otp $otp) {
                    $this->assertNotNull($otp->getConsumedAt());
                    $this->assertEquals($otp->getMaxAttempts(), $otp->getAttempts());
                });
        } else {
            $otpRepository->expects($this->never())
                ->method('put');
        }

        $this->assertEquals(
            $expected,
            $provider->verify(new Verification(
                resource: $resource,
                purpose: Purpose::EmailVerification,
                code: '123456',
            ))
        );
    }

    /**
     * @throws \DateMalformedStringException
     */
    public static function provideVerifyCases(): \Generator
    {
        yield 'otp not found' => [
            'otp' => null,
            'putCall' => false,
            'expected' => VerificationResult::NotFound,
        ];

        yield 'revoked otp' => [
            'otp' => OtpMother::create(
                attempts: 0,
                maxAttempts: 1,
                revoked: true,
            ),
            'putCall' => false,
            'expected' => VerificationResult::InvalidCode,
        ];

        yield 'max attempts reached' => [
            'otp' => OtpMother::create(
                attempts: 1,
                maxAttempts: 1,
                revoked: false,
            ),
            'putCall' => false,
            'expected' => VerificationResult::MaxAttemptsReached,
        ];

        yield 'invalid hash otp' => [
            'otp' => OtpMother::create(
                codeSecret: hash_hmac('sha256', 'AB1234', 'secret'),
                attempts: 0,
                maxAttempts: 1,
                revoked: false,
            ),
            'putCall' => true,
            'expected' => VerificationResult::InvalidCode,
        ];

        yield 'expired otp' => [
            'otp' => OtpMother::create(
                codeSecret: hash_hmac('sha256', '123456', 'secret'),
                attempts: 0,
                maxAttempts: 1,
                revoked: false,
                expiresAt: new \DateTimeImmutable()->modify('-1 minutes'),
            ),
            'putCall' => true,
            'expected' => VerificationResult::Expired,
        ];

        yield 'valid otp' => [
            'otp' => OtpMother::create(
                codeSecret: hash_hmac('sha256', '123456', 'secret'),
                attempts: 0,
                maxAttempts: 1,
                revoked: false,
                expiresAt: new \DateTimeImmutable()->modify('+1 hour'),
            ),
            'putCall' => true,
            'expected' => VerificationResult::Valid,
        ];
    }

    private function getProvider(
        ?OtpRepository $otpRepository = null,
        ?UuidGenerator $uuidGenerator = null,
        ?DeliveryChannelDispatcher $deliveryChannelDispatcher = null,
    ): DatabaseOtpProvider {
        if (null === $otpRepository) {
            $otpRepository = $this->createMock(OtpRepository::class);
            $otpRepository->expects($this->never())
                ->method($this->anything());
        }

        if (null === $uuidGenerator) {
            $uuidGenerator = $this->createMock(UuidGenerator::class);
            $uuidGenerator->expects($this->never())
                ->method($this->anything());
        }

        if (null === $deliveryChannelDispatcher) {
            $deliveryChannelDispatcher = $this->createMock(DeliveryChannelDispatcher::class);
            $deliveryChannelDispatcher->expects($this->never())
                ->method($this->anything());
        }

        return new DatabaseOtpProvider(
            otpRepository: $otpRepository,
            uuidGenerator: $uuidGenerator,
            deliveryChannelDispatcher: $deliveryChannelDispatcher,
            secret: 'secret',
        );
    }
}
