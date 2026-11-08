<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Security;

use App\Domain\Security\Exceptions\RefreshTokenNotFoundException;
use App\Domain\Security\RefreshToken;
use App\Domain\Security\RefreshTokenCollection;
use App\Domain\Security\RefreshTokenCriteria;
use App\Domain\Security\RefreshTokenRepository;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Infrastructure\Persistence\DBALDateTimeFormatter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Query\QueryBuilder;

readonly class DBALRefreshTokenRepository implements RefreshTokenRepository
{
    public function __construct(
        private Connection $connection,
        private string $refreshTokenTableName,
    ) {
    }

    public function put(RefreshToken $token): void
    {
        try {
            $this->connection->insert(
                $this->refreshTokenTableName,
                $this->fromRefreshTokenToArray($token)
            );
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @throws RefreshTokenNotFoundException
     * @throws \DateMalformedStringException
     * @throws InfrastructureException
     */
    public function findOneBy(RefreshTokenCriteria $criteria): RefreshToken
    {
        try {
            $tokenArray = $this
                ->getQueryBuilderCriteria($criteria)
                ->executeQuery()
                ->fetchAssociative();

            if (false === $tokenArray) {
                throw new RefreshTokenNotFoundException();
            }

            return $this->fromArrayToRefreshToken($tokenArray);
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @throws \DateMalformedStringException
     * @throws InfrastructureException
     * @throws CollectionException
     */
    public function findBy(RefreshTokenCriteria $criteria): RefreshTokenCollection
    {
        try {
            $tokensArray = $this
                ->getQueryBuilderCriteria($criteria)
                ->executeQuery()
                ->fetchAllAssociative();

            return new RefreshTokenCollection(
                array_map(
                    fn (array $tokenArray) => $this->fromArrayToRefreshToken($tokenArray),
                    $tokensArray
                )
            );
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    public function delete(RefreshToken $refreshToken): void
    {
        try {
            $this->connection->delete(
                $this->refreshTokenTableName,
                [
                    'refresh_token' => $refreshToken->getRefreshToken(),
                ]
            );
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fromRefreshTokenToArray(RefreshToken $token): array
    {
        return [
            'refresh_token' => $token->getRefreshToken(),
            'username' => $token->getUsername(),
            'valid' => DBALDateTimeFormatter::format($token->getValid()),
        ];
    }

    /**
     * @param array<string, mixed> $tokenArray
     *
     * @throws \DateMalformedStringException
     */
    private function fromArrayToRefreshToken(array $tokenArray): RefreshToken
    {
        return new RefreshToken(
            refreshToken: $tokenArray['refresh_token'],
            username: $tokenArray['username'],
            valid: DBALDateTimeFormatter::parse($tokenArray['valid']),
        );
    }

    private function getQueryBuilderCriteria(RefreshTokenCriteria $criteria): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder
            ->select('t.*')
            ->from($this->refreshTokenTableName, 't');

        if (null !== $criteria->getToken()) {
            $queryBuilder
                ->andWhere('t.refresh_token = :token')
                ->setParameter('token', $criteria->getToken());
        }

        if (null !== $criteria->getUsername()) {
            $queryBuilder
                ->andWhere('t.username = :username')
                ->setParameter('username', $criteria->getUsername());
        }

        return $queryBuilder;
    }
}
