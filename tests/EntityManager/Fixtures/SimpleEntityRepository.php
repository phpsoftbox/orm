<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\IdentifierInterface;
use Ramsey\Uuid\UuidInterface;

use function array_values;
use function is_int;

final class SimpleEntityRepository implements EntityRepositoryInterface
{
    public int $findCalls = 0;

    /**
     * @param array<int, SimpleEntity> $storage
     */
    public function __construct(
        private array $storage = [],
    ) {
    }

    public function find(int|UuidInterface|array|IdentifierInterface $id): ?EntityInterface
    {
        $this->findCalls++;

        $key = is_int($id) ? $id : null;
        if ($key === null) {
            return null;
        }

        return $this->storage[$key] ?? null;
    }

    public function exists(int|UuidInterface|array|IdentifierInterface $id): bool
    {
        $key = is_int($id) ? $id : null;
        if ($key === null) {
            return false;
        }

        return isset($this->storage[$key]);
    }

    public function all(): EntityCollection
    {
        return new EntityCollection(array_values($this->storage));
    }

    public function persist(EntityInterface $entity): void
    {
        // не нужно для теста
    }

    public function remove(EntityInterface $entity): void
    {
        // не нужно для теста
    }
}
