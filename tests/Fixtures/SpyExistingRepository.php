<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\IdentifierInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Репозиторий-шпион, который всегда считает запись существующей в БД (exists() === true).
 *
 * Нужен для тестов EntityManager/UnitOfWork, чтобы persist() шёл по ветке UPDATE, а не INSERT.
 */
final class SpyExistingRepository implements EntityRepositoryInterface
{
    /** @var list<EntityInterface> */
    public array $persisted = [];

    /** @var list<EntityInterface> */
    public array $removed = [];

    public function persist(EntityInterface $entity): void
    {
        $this->persisted[] = $entity;
    }

    public function remove(EntityInterface $entity): void
    {
        $this->removed[] = $entity;
    }

    public function find(int|UuidInterface|array|IdentifierInterface $id): ?EntityInterface
    {
        return null;
    }

    public function exists(int|UuidInterface|array|IdentifierInterface $id): bool
    {
        return true;
    }

    public function all(): EntityCollection
    {
        return new EntityCollection([]);
    }
}
