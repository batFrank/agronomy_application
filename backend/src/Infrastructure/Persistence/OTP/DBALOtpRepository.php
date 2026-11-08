<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OTP;

use App\Domain\OTP\Exception\OtpNotFoundException;
use App\Domain\OTP\Otp;
use App\Domain\OTP\OtpCollection;
use App\Domain\OTP\OtpCriteria;
use App\Domain\OTP\OtpRepository;
use App\Domain\OTP\Purpose;
use App\Domain\Shared\Criteria\Condition\Condition;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Resource\Resource;
use App\Domain\Shared\Resource\ResourceType;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Infrastructure\Persistence\DBALCommonCriteriaBuilder;
use App\Infrastructure\Persistence\DBALDateTimeFormatter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Query\QueryBuilder;

final readonly class DBALOtpRepository implements OtpRepository
{
    private const array RESOURCE_TYPE_MAP = [
        'USER' => ResourceType::User,
    ];

    private const array PURPOSE_MAP = [
        'LOGIN_2FA' => Purpose::Login2FA,
        'EMAIL_VERIFICATION' => Purpose::EmailVerification,
        'PASSWORD_RESET' => Purpose::PasswordReset,
    ];

    public function __construct(
        private Connection $connection,
        private string $otpTableName,
    ) {
    }

    #[\Override]
    public function put(Otp $otp): void
    {
        try {
            $this->findOneBy(OtpCriteria::createById($otp->getId()));

            $this->update($otp);
        } catch (OtpNotFoundException) {
            $this->insert($otp);
        }
    }

    #[\Override]
    public function findOneBy(OtpCriteria $criteria): Otp
    {
        try {
            $userArray = $this
                ->getQueryBuilderByCriteria($criteria)
                ->executeQuery()
                ->fetchAssociative();

            if (false === $userArray) {
                throw new OtpNotFoundException();
            }

            return $this->fromArrayToOtp($userArray);
        } catch (DBALException|InvalidUuidException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    #[\Override]
    public function findBy(OtpCriteria $criteria): OtpCollection
    {
        try {
            $result = $this
                ->getQueryBuilderByCriteria($criteria)
                ->executeQuery()
                ->fetchAllAssociative();

            return new OtpCollection(
                array_map(
                    fn (array $values) => $this->fromArrayToOtp($values),
                    $result
                )
            );
        } catch (DBALException|InvalidUuidException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    #[\Override]
    public function delete(Otp $otp): void
    {
        try {
            $this->connection->delete(
                table: $this->otpTableName,
                criteria: ['id' => $otp->getId()->value()],
            );
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @throws InfrastructureException
     */
    private function insert(Otp $otp): void
    {
        try {
            $this->connection->insert(
                table: $this->otpTableName,
                data: $this->fromOtpToArray($otp),
            );
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @throws InfrastructureException
     */
    private function update(Otp $otp): void
    {
        try {
            $this->connection->update(
                table: $this->otpTableName,
                data: $this->fromOtpToArray($otp),
                criteria: ['id' => $otp->getId()->value()],
            );
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InfrastructureException
     */
    private function fromOtpToArray(Otp $otp): array
    {
        return [
            'id' => $otp->getId()->value(),
            'resource_id' => $otp->getResource()->getId()->value(),
            'resource_type' => $this->fromResourceTypeToString($otp->getResource()->getType()),
            'purpose' => $this->fromPurposeToString($otp->getPurpose()),
            'code_secret' => $otp->getCodeSecret(),
            'attempts' => $otp->getAttempts(),
            'max_attempts' => $otp->getMaxAttempts(),
            'revoked' => $otp->isRevoked() ? 1 : 0,
            'expires_at' => DBALDateTimeFormatter::format($otp->getExpiresAt()),
            'consumed_at' => DBALDateTimeFormatter::format($otp->getConsumedAt()),
            'created_at' => DBALDateTimeFormatter::format($otp->getCreatedAt()),
        ];
    }

    /**
     * @param array<string, mixed> $values
     *
     * @throws InvalidUuidException
     * @throws InfrastructureException
     */
    private function fromArrayToOtp(array $values): Otp
    {
        return new Otp(
            id: new Uuid($values['id']),
            resource: new Resource(
                id: new Uuid($values['resource_id']),
                type: self::RESOURCE_TYPE_MAP[$values['resource_type']],
            ),
            purpose: self::PURPOSE_MAP[$values['purpose']],
            codeSecret: $values['code_secret'],
            attempts: $values['attempts'],
            maxAttempts: $values['max_attempts'],
            revoked: filter_var($values['revoked'], FILTER_VALIDATE_BOOLEAN),
            expiresAt: DBALDateTimeFormatter::parse($values['expires_at'])
                ?? throw new InfrastructureException('Invalid expires_at format'),
            consumedAt: DBALDateTimeFormatter::parse($values['consumed_at']),
            createdAt: DBALDateTimeFormatter::parse($values['created_at'])
                ?? throw new InfrastructureException('Invalid created_at format'),
        );
    }

    /**
     * @throws InfrastructureException
     */
    private function fromResourceTypeToString(ResourceType $resourceType): string
    {
        $stringResource = array_search($resourceType, self::RESOURCE_TYPE_MAP);
        if (false === $stringResource) {
            throw new InfrastructureException('Unsupported resource type.');
        }

        return $stringResource;
    }

    /**
     * @throws InfrastructureException
     */
    private function fromPurposeToString(Purpose $purpose): string
    {
        $stringPurpose = array_search($purpose, self::PURPOSE_MAP);
        if (false === $stringPurpose) {
            throw new InfrastructureException('Unsupported purpose.');
        }

        return $stringPurpose;
    }

    /**
     * @throws InfrastructureException
     */
    private function getQueryBuilderByCriteria(OtpCriteria $criteria): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('t.*')
            ->from($this->otpTableName, 't');

        if (null !== $criteria->getResource()) {
            $queryBuilder->andWhere('t.resource_id = :resourceId AND t.resource_type = :resourceType')
                ->setParameter('resourceId', $criteria->getResource()->getId()->value())
                ->setParameter('resourceType', $this->fromResourceTypeToString($criteria->getResource()->getType()));
        }

        if (null !== $criteria->getPurpose()) {
            $queryBuilder->andWhere('t.purpose = :purpose')
                ->setParameter('purpose', $this->fromPurposeToString($criteria->getPurpose()));
        }

        if (null !== $criteria->getCodeSecret()) {
            $queryBuilder->andWhere('t.code_secret = :codeSecret')
                ->setParameter('codeSecret', $criteria->getCodeSecret());
        }

        if (null !== $criteria->getRevoked()) {
            $queryBuilder->andWhere('t.revoked = :revoked')
                ->setParameter('revoked', $criteria->getRevoked() ? 1 : 0);
        }

        if (null !== $criteria->getConsumedAt()) {
            switch ($criteria->getConsumedAt()->getCondition()) {
                case Condition::IsNull:
                    $queryBuilder->andWhere('t.consumed_at IS NULL');
                    break;
                case Condition::IsNotNull:
                    $queryBuilder->andWhere('t.consumed_at IS NOT NULL');
                    break;
                default:
                    $value = $criteria->getConsumedAt()->value();
                    if (!$value instanceof \DateTimeImmutable) {
                        throw new InfrastructureException('Invalid consumed_at value.');
                    }
                    $queryBuilder->andWhere('t.consumed_at = :consumedAt')
                        ->setParameter('consumedAt', DBALDateTimeFormatter::format($value));
                    break;
            }
        }

        DBALCommonCriteriaBuilder::filterByCommonCriteria($criteria, $queryBuilder);

        return $queryBuilder;
    }
}
