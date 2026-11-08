<?php

declare(strict_types=1);

namespace App\Application\Hydrator\User;

use App\Application\Hydrator\Hydrator;
use App\Application\Hydrator\HydratorPriority;
use App\Domain\Shared\Collection\Collection;
use App\Domain\Shared\Collection\CollectionException;
use App\Domain\Shared\Criteria\CriteriaException;
use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Hydrator\HydrationCriteria;
use App\Domain\Shared\Hydrator\HydratorException;
use App\Domain\Shared\UserCard\UserCard;
use App\Domain\Shared\Uuid\UuidCollection;
use App\Domain\User\User;
use App\Domain\User\UserCollection;
use App\Domain\User\UserCriteria;
use App\Domain\User\UserHydrationCriteria;
use App\Domain\User\UserRepository;

/**
 * @implements Hydrator<User>
 */
readonly class UserCreatorHydrator implements Hydrator
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    #[\Override]
    public function supports(HydrationCriteria $criteria): bool
    {
        return $criteria instanceof UserHydrationCriteria
            && $criteria->shouldHydrateCreator();
    }

    #[\Override]
    public function getPriority(): HydratorPriority
    {
        return HydratorPriority::First;
    }

    /**
     * @param Collection<User> $collection
     *
     * @throws HydratorException
     * @throws InfrastructureException
     */
    #[\Override]
    public function hydrate(Collection $collection, HydrationCriteria $criteria): void
    {
        if (!$collection instanceof UserCollection) {
            throw new HydratorException('UserCreatorHydrator expects a UserCollection.');
        }

        $creatorIds = $this->extractUniqueCreatorIds($collection);
        if ($creatorIds->isEmpty()) {
            return;
        }

        $creatorCardsById = $this->getCreatorCardsById($creatorIds);

        foreach ($collection->all() as $user) {
            $createdByValue = $user->getCreatedBy()?->value();
            $user->setCreator(
                null !== $createdByValue ? ($creatorCardsById[$createdByValue] ?? null) : null
            );
        }
    }

    /**
     * @throws CollectionException
     */
    private function extractUniqueCreatorIds(UserCollection $collection): UuidCollection
    {
        $creatorIdsByValue = [];

        foreach ($collection->all() as $user) {
            $creatorId = $user->getCreatedBy();
            if (null !== $creatorId) {
                $creatorIdsByValue[$creatorId->value()] = $creatorId;
            }
        }

        return new UuidCollection(array_values($creatorIdsByValue));
    }

    /**
     * @return array<string, UserCard>
     *
     * @throws InfrastructureException
     * @throws HydratorException
     */
    private function getCreatorCardsById(UuidCollection $creatorIds): array
    {
        try {
            $creators = $this->userRepository->findBy(
                UserCriteria::createByIds($creatorIds)
            );
        } catch (CriteriaException $exception) {
            throw HydratorException::fromPrevious($exception);
        }

        $creatorCardsById = [];
        foreach ($creators->all() as $creator) {
            $creatorCard = $creator->getUserCard();
            $creatorCardsById[$creatorCard->getId()->value()] = $creatorCard;
        }

        return $creatorCardsById;
    }
}
