<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Security;

use App\Domain\Security\Exceptions\RefreshTokenNotFoundException;
use App\Domain\Security\RefreshToken;
use App\Domain\Security\RefreshTokenCollection;
use App\Domain\Security\RefreshTokenCriteria;
use App\Domain\Security\RefreshTokenRepository;
use App\Domain\Shared\Collection\CollectionException;
use App\Infrastructure\Persistence\InMemoryCommonCriteriaBuilder;

class InMemoryRefreshTokenRepository implements RefreshTokenRepository
{
    private RefreshTokenCollection $collection;

    /**
     * @throws CollectionException
     */
    public function __construct()
    {
        $this->collection = new RefreshTokenCollection([]);
    }

    public function put(RefreshToken $token): void
    {
        $this->collection->append($token);
    }

    public function findOneBy(RefreshTokenCriteria $criteria): RefreshToken
    {
        $filteredTokens = $this->filterByCriteria($criteria);

        if (null === $filteredTokens->first()) {
            throw new RefreshTokenNotFoundException();
        }

        return $filteredTokens->first();
    }

    /**
     * @throws CollectionException
     */
    public function findBy(RefreshTokenCriteria $criteria): RefreshTokenCollection
    {
        $filteredTokens = $this->filterByCriteria($criteria);

        return new RefreshTokenCollection($filteredTokens->all());
    }

    /**
     * @throws CollectionException
     */
    public function delete(RefreshToken $refreshToken): void
    {
        $this->collection = $this->collection->filter(
            fn (RefreshToken $token) => $token->getRefreshToken() !== $refreshToken->getRefreshToken()
        );
    }

    /**
     * @throws CollectionException
     */
    private function filterByCriteria(RefreshTokenCriteria $criteria): RefreshTokenCollection
    {
        $collection = $this->collection->filter(
            fn (RefreshToken $token): bool => (
                null === $criteria->getUsername() || $token->getUsername() === $criteria->getUsername()
            ) && (
                null === $criteria->getToken() || $token->getRefreshToken() === $criteria->getToken()
            )
            && (
                null !== $token->getValid() && $token->getValid() > new \DateTimeImmutable()
            )
        );

        $collection = InMemoryCommonCriteriaBuilder::filterByCommonCriteria(
            criteria: $criteria,
            collection: $collection,
            onlyFilter: true,
        );

        /* @var RefreshTokenCollection $collection */
        return $collection->map(
            fn (RefreshToken $token) => new RefreshToken(
                refreshToken: $token->getRefreshToken(),
                username: $token->getUsername(),
                valid: $token->getValid(),
            )
        );
    }
}
