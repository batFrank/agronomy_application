<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Translation;

use App\Domain\Shared\Exception\InfrastructureException;
use App\Domain\Shared\Locale\InvalidLocaleException;
use App\Domain\Shared\Uuid\InvalidUuidException;
use App\Domain\Shared\Uuid\Uuid;
use App\Domain\Translation\Exception\TranslationNotFoundException;
use App\Domain\Translation\Exception\TranslationRepositoryException;
use App\Domain\Translation\Translation;
use App\Domain\Translation\TranslationCollection;
use App\Domain\Translation\TranslationCriteria;
use App\Domain\Translation\TranslationRepository;
use App\Infrastructure\Persistence\DBALCommonCriteriaBuilder;
use App\Infrastructure\Persistence\DBALDateTimeFormatter;
use App\Infrastructure\Persistence\DBALLocaleTransformer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Query\QueryBuilder;

readonly class DBALTranslationRepository implements TranslationRepository
{
    public function __construct(
        private Connection $connection,
        private string $translationTableName,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function put(Translation $translation): void
    {
        try {
            $this->findOneBy(TranslationCriteria::createById($translation->getId()));
            $this->update($translation);
        } catch (TranslationNotFoundException) {
            $this->create($translation);
        }
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function findOneBy(TranslationCriteria $criteria): Translation
    {
        try {
            $result = $this->getQueryBuilderByCriteria($criteria)
                ->executeQuery()
                ->fetchAssociative();

            if (false === $result) {
                throw new TranslationNotFoundException();
            }

            return $this->fromArrayToTranslation($result);
        } catch (DBALException|InvalidUuidException|InvalidLocaleException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function findBy(TranslationCriteria $criteria): TranslationCollection
    {
        try {
            $result = $this->getQueryBuilderByCriteria($criteria)
                ->executeQuery()
                ->fetchAllAssociative();

            return new TranslationCollection(
                array_map(
                    fn (array $values) => $this->fromArrayToTranslation($values),
                    $result,
                )
            );
        } catch (DBALException|InvalidUuidException|InvalidLocaleException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @throws InfrastructureException
     * @throws TranslationNotFoundException
     */
    public function delete(Translation $translation): void
    {
        $translation = $this->findOneBy(
            TranslationCriteria::createById($translation->getId())
        );

        $translation->markAsDeleted();

        $this->update($translation);
    }

    /**
     * @throws InfrastructureException
     */
    private function create(Translation $translation): void
    {
        try {
            $this->connection->insert(
                table: $this->translationTableName,
                data: $this->fromTranslationToArray($translation),
            );
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    /**
     * @throws InfrastructureException
     */
    private function update(Translation $translation): void
    {
        try {
            $data = $this->fromTranslationToArray($translation);
            unset($data['id']);
            unset($data['created_at']);

            $this->connection->update(
                table: $this->translationTableName,
                data: $data,
                criteria: ['id' => $translation->getId()->value()],
            );
        } catch (DBALException $e) {
            throw InfrastructureException::fromPrevious($e);
        }
    }

    private function getQueryBuilderByCriteria(TranslationCriteria $criteria): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('t.*')
            ->from($this->translationTableName, 't')
            ->andWhere('t.deleted_at IS NULL');

        if (null !== $criteria->getEntity()) {
            $queryBuilder
                ->andWhere('t.entity_type = :entity')
                ->andWhere('t.entity_id = :entityId')
                ->setParameter('entity', $criteria->getEntity()::class)
                ->setParameter('entityId', $criteria->getEntity()->getId());
        }

        if (null !== $criteria->getEntityCollection()) {
            $orConditions = [];
            $parameters = [];
            $index = 0;

            foreach ($criteria->getEntityCollection()->all() as $entity) {
                $orConditions[] = "(t.entity_type = :entity_type_{$index} AND t.entity_id = :entity_id_{$index})";
                $parameters["entity_type_{$index}"] = $entity::class;
                $parameters["entity_id_{$index}"] = $entity->getId();
                ++$index;
            }

            if (!empty($orConditions)) {
                $queryBuilder->andWhere('(' . implode(' OR ', $orConditions) . ')');
                foreach ($parameters as $key => $value) {
                    $queryBuilder->setParameter($key, $value);
                }
            }
        }

        if (null !== $criteria->getField()) {
            $queryBuilder->andWhere('t.field = :field')
                ->setParameter('field', $criteria->getField());
        }

        if (null !== $criteria->getLocale()) {
            $queryBuilder->andWhere('t.locale = :locale')
                ->setParameter(
                    'locale',
                    DBALLocaleTransformer::fromLocaleToString($criteria->getLocale())
                );
        }

        DBALCommonCriteriaBuilder::filterByCommonCriteria(
            criteria: $criteria,
            queryBuilder: $queryBuilder
        );

        return $queryBuilder;
    }

    /**
     * Las excepciones de value object que surjan aquí vienen de una fila ya persistida, no de la
     * petición: quien llama las envuelve en InfrastructureException para que sigan clasificando
     * como error de servidor.
     *
     * @param array<string, mixed> $data
     *
     * @throws InfrastructureException
     * @throws InvalidUuidException
     * @throws InvalidLocaleException
     */
    private function fromArrayToTranslation(array $data): Translation
    {
        $createdAt = DBALDateTimeFormatter::parse($data['created_at']);
        if (null === $createdAt) {
            throw new TranslationRepositoryException('Invalid created_at format');
        }

        return new Translation(
            id: new Uuid($data['id']),
            entityType: $data['entity_type'],
            entityId: new Uuid($data['entity_id']),
            field: $data['field'],
            locale: DBALLocaleTransformer::fromStringToLocale($data['locale']),
            translation: $data['translation'],
            createdAt: $createdAt,
            updatedAt: DBALDateTimeFormatter::parse($data['updated_at']),
            deletedAt: DBALDateTimeFormatter::parse($data['deleted_at']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fromTranslationToArray(Translation $translation): array
    {
        return [
            'id' => $translation->getId()->value(),
            'entity_type' => $translation->getEntityType(),
            'entity_id' => $translation->getEntityId()->value(),
            'field' => $translation->getField(),
            'locale' => DBALLocaleTransformer::fromLocaleToString($translation->getLocale()),
            'translation' => $translation->getTranslation(),
            'created_at' => DBALDateTimeFormatter::format($translation->getCreatedAt()),
            'updated_at' => DBALDateTimeFormatter::format($translation->getUpdatedAt()),
            'deleted_at' => DBALDateTimeFormatter::format($translation->getDeletedAt()),
        ];
    }
}
