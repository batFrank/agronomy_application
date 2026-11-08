<?php

declare(strict_types=1);

namespace App\Infrastructure\Response;

use App\Domain\Shared\Collection\PaginatedCollection;
use App\Domain\Shared\Criteria\Criteria;

/**
 * @template T
 */
class ApiResponseContent
{
    /**
     * @param array<int, mixed>|array<string, mixed>|null $data
     * @param array<string, mixed>                        $metadata
     */
    public function __construct(
        private readonly ?array $data = null,
        private readonly string $message = '',
        private readonly int $errorCode = 0,
        private array $metadata = [],
    ) {
    }

    /**
     * @param array<array>|array<int, mixed>|array<string, mixed> $data
     *
     * @return ApiResponseContent<T>
     */
    public static function createFromData(array $data): self
    {
        return new self($data);
    }

    /**
     * @return ApiResponseContent<T>
     */
    public static function createFromMessage(string $message, int $errorCode = 0): self
    {
        return new self(
            message: $message,
            errorCode: $errorCode,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return ApiResponseContent<T>
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return ApiResponseContent<T>
     */
    public function addMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(
            null !== $this->data ? ['data' => $this->data] : [],
            empty($this->message) ? [] : ['message' => $this->message],
            0 === $this->errorCode ? [] : ['error' => $this->errorCode],
            empty($this->metadata) ? [] : ['metadata' => $this->metadata],
        );
    }

    /**
     * @param PaginatedCollection<T> $paginatedCollection
     * @param Criteria<T>            $criteria
     *
     * @return ApiResponseContent<T>
     */
    public function addPaginationMetadata(PaginatedCollection $paginatedCollection, Criteria $criteria): self
    {
        if (null === $criteria->getPagination()) {
            return $this;
        }

        return $this->addMetadata([
            'page' => $criteria->getPagination()->page(),
            'total_pages' => (int) ceil($paginatedCollection->getTotalItems() / $criteria->getPagination()->limit()),
            'limit' => $criteria->getPagination()->limit(),
            'total' => $paginatedCollection->getTotalItems(),
        ]);
    }
}
