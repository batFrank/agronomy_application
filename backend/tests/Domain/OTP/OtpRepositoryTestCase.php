<?php

declare(strict_types=1);

namespace App\Tests\Domain\OTP;

use App\Domain\OTP\Exception\OtpNotFoundException;
use App\Domain\OTP\OtpCriteria;
use App\Domain\OTP\OtpRepository;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Criteria\Condition\Condition;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Resource\ResourceType;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Tests\Mother\Domain\OTP\OtpMother;
use App\Tests\Mother\Domain\Shared\Resource\ResourceMother;
use App\Tests\Mother\Domain\Shared\Uuid\UuidMother;
use PHPUnit\Framework\Attributes\CoversClassesThatImplementInterface;
use PHPUnit\Framework\TestCase;

#[CoversClassesThatImplementInterface(OtpRepository::class)]
abstract class OtpRepositoryTestCase extends TestCase
{
    abstract protected function getRepository(): OtpRepository;

    /**
     * @throws InfrastructureException
     * @throws OtpNotFoundException
     */
    public function testPut(): void
    {
        $repository = $this->getRepository();

        $otp1 = OtpMother::create();
        $otp2 = OtpMother::create();
        $otp3 = OtpMother::create();

        $repository->put($otp1);
        $repository->put($otp2);
        $repository->put($otp3);

        $this->assertEquals($otp1, $repository->findOneBy(OtpCriteria::createById($otp1->getId())));
        $this->assertEquals($otp2, $repository->findOneBy(OtpCriteria::createById($otp2->getId())));
        $this->assertEquals($otp3, $repository->findOneBy(OtpCriteria::createById($otp3->getId())));

        $this->assertCount(3, $repository->findBy(OtpCriteria::createEmpty()));

        $otp2Update = OtpMother::create(
            id: $otp2->getId(),
            resource: $otp2->getResource(),
            purpose: $otp2->getPurpose(),
            codeSecret: $otp2->getCodeSecret(),
            attempts: $otp2->getAttempts(),
            maxAttempts: $otp2->getMaxAttempts(),
            expiresAt: $otp2->getExpiresAt(),
            consumedAt: new \DateTimeImmutable(),
            createdAt: $otp2->getCreatedAt(),
        );

        $repository->put($otp2Update);

        $result = $repository->findOneBy(OtpCriteria::createById($otp2->getId()));

        $this->assertEquals($otp2Update, $result);
        $this->assertNotEquals($otp2, $result);
    }

    /**
     * @throws InvalidUuidException
     * @throws OtpNotFoundException
     * @throws InfrastructureException
     */
    public function testFindOneBy(): void
    {
        $repository = $this->getRepository();
        $entityId1 = UuidMother::create();
        $entityId2 = UuidMother::create();

        $codeSecret1 = 'code-secret-1';
        $codeSecret2 = 'code-secret-2';
        $codeSecret3 = 'code-secret-3';

        $otp1 = OtpMother::create(
            resource: ResourceMother::create(
                id: $entityId1,
                type: ResourceType::User,
            ),
            purpose: Purpose::Login2FA,
            codeSecret: $codeSecret1,
        );

        $otp2 = OtpMother::create(
            resource: ResourceMother::create(
                id: $entityId2,
                type: ResourceType::User,
            ),
            purpose: Purpose::EmailVerification,
            codeSecret: $codeSecret2,
        );

        $otp3 = OtpMother::create(
            resource: ResourceMother::create(
                id: $entityId2,
                type: ResourceType::User,
            ),
            purpose: Purpose::PasswordReset,
            codeSecret: $codeSecret3,
        );

        $repository->put($otp1);
        $repository->put($otp2);
        $repository->put($otp3);

        $this->assertEquals($otp1, $repository->findOneBy(OtpCriteria::createById($otp1->getId())));
        $this->assertEquals($otp2, $repository->findOneBy(OtpCriteria::createById($otp2->getId())));
        $this->assertEquals($otp3, $repository->findOneBy(OtpCriteria::createById($otp3->getId())));

        $this->assertEquals($otp1, $repository->findOneBy(OtpCriteria::createEmpty()->filterByResource($otp1->getResource())));
        $this->assertEquals($otp2, $repository->findOneBy(OtpCriteria::createEmpty()->filterByResource($otp2->getResource())));
        $this->assertEquals($otp2, $repository->findOneBy(OtpCriteria::createEmpty()->filterByResource($otp3->getResource())));

        $this->assertEquals($otp1, $repository->findOneBy(OtpCriteria::createEmpty()->filterByCodeSecret($codeSecret1)));
        $this->assertEquals($otp2, $repository->findOneBy(OtpCriteria::createEmpty()->filterByCodeSecret($codeSecret2)));
        $this->assertEquals($otp3, $repository->findOneBy(OtpCriteria::createEmpty()->filterByCodeSecret($codeSecret3)));

        $this->assertEquals(
            $otp3,
            $repository->findOneBy(
                OtpCriteria::createEmpty()
                    ->filterByResource($otp3->getResource())
                    ->filterByPurpose(Purpose::PasswordReset)
                    ->filterByCodeSecret($codeSecret3)
            )
        );

        $this->expectException(OtpNotFoundException::class);
        $repository->findOneBy(
            OtpCriteria::createEmpty()
                ->filterByResource($otp3->getResource())
                ->filterByPurpose(Purpose::EmailVerification)
                ->filterByCodeSecret($codeSecret3)
        );
    }

    /**
     * @throws InfrastructureException
     * @throws InvalidUuidException
     */
    public function testFindBy(): void
    {
        $repository = $this->getRepository();
        $entityId1 = UuidMother::create();
        $entityId2 = UuidMother::create();

        $codeSecret1 = 'code-secret-1';
        $codeSecret2 = 'code-secret-2';
        $codeSecret3 = 'code-secret-3';

        $otp1 = OtpMother::create(
            resource: ResourceMother::create(
                id: $entityId1,
                type: ResourceType::User,
            ),
            purpose: Purpose::Login2FA,
            codeSecret: $codeSecret1,
        );

        $otp2 = OtpMother::create(
            resource: ResourceMother::create(
                id: $entityId2,
                type: ResourceType::User,
            ),
            purpose: Purpose::EmailVerification,
            codeSecret: $codeSecret2,
        );

        $otp3 = OtpMother::create(
            resource: ResourceMother::create(
                id: $entityId2,
                type: ResourceType::User,
            ),
            purpose: Purpose::PasswordReset,
            codeSecret: $codeSecret3,
            revoked: true,
        );

        $otp4 = OtpMother::create(
            resource: ResourceMother::create(
                id: $entityId2,
                type: ResourceType::User,
            ),
            purpose: Purpose::PasswordReset,
        );

        $otp5 = OtpMother::create(
            resource: ResourceMother::create(
                id: $entityId2,
                type: ResourceType::User,
            ),
            purpose: Purpose::PasswordReset,
            consumedAt: new \DateTimeImmutable(),
        );

        $repository->put($otp1);
        $repository->put($otp2);
        $repository->put($otp3);
        $repository->put($otp4);
        $repository->put($otp5);

        $this->assertCount(5, $repository->findBy(OtpCriteria::createEmpty()));

        $result = $repository->findBy(OtpCriteria::createEmpty()->filterByRevoked(true));
        $this->assertCount(1, $result);
        $this->assertEquals($otp3, $result->first());

        $result = $repository->findBy(
            OtpCriteria::createEmpty()
                ->filterByResource(ResourceMother::create(
                    id: $entityId2,
                    type: ResourceType::User,
                ))
        );
        $this->assertCount(4, $result);
        $this->assertEquals($otp2, $result->first());
        $this->assertEquals($otp5, $result->last());

        $result = $repository->findBy(
            OtpCriteria::createEmpty()
                ->filterByResource(ResourceMother::create(
                    id: $entityId1,
                    type: ResourceType::User,
                ))
        );
        $this->assertCount(1, $result);
        $this->assertEquals($otp1, $result->first());

        $result = $repository->findBy(
            OtpCriteria::createEmpty()
                ->filterByResource(ResourceMother::create(
                    id: $entityId2,
                    type: ResourceType::User,
                ))
                ->filterByPurpose(Purpose::PasswordReset)
        );
        $this->assertCount(3, $result);
        $this->assertEquals($otp3, $result->first());
        $this->assertEquals($otp5, $result->last());

        $result = $repository->findBy(
            OtpCriteria::createEmpty()
                ->filterByResource(ResourceMother::create(
                    id: $entityId2,
                    type: ResourceType::User,
                ))
                ->filterByPurpose(Purpose::PasswordReset)
                ->filterByRevoked(true)
        );
        $this->assertCount(1, $result);
        $this->assertEquals($otp3, $result->first());

        $result = $repository->findBy(
            OtpCriteria::createEmpty()
                ->filterByResource(ResourceMother::create(
                    id: $entityId2,
                    type: ResourceType::User,
                ))
                ->filterByPurpose(Purpose::PasswordReset)
                ->filterByRevoked(false)
                ->filterByConsumedAt(value: null, condition: Condition::IsNull)
        );
        $this->assertCount(1, $result);
        $this->assertEquals($otp4, $result->first());
    }

    /**
     * @throws InfrastructureException
     * @throws OtpNotFoundException
     */
    public function testDelete(): void
    {
        $repository = $this->getRepository();

        $otp1 = OtpMother::create();
        $otp2 = OtpMother::create();
        $otp3 = OtpMother::create();

        $repository->put($otp1);
        $repository->put($otp2);
        $repository->put($otp3);

        $this->assertCount(3, $repository->findBy(OtpCriteria::createEmpty()));

        $this->assertEquals($otp1, $repository->findOneBy(OtpCriteria::createById($otp1->getId())));
        $this->assertEquals($otp2, $repository->findOneBy(OtpCriteria::createById($otp2->getId())));
        $this->assertEquals($otp3, $repository->findOneBy(OtpCriteria::createById($otp3->getId())));

        $repository->delete($otp2);
        $this->assertCount(2, $repository->findBy(OtpCriteria::createEmpty()));
        $this->expectException(OtpNotFoundException::class);
        $repository->findOneBy(OtpCriteria::createById($otp2->getId()));
    }
}
