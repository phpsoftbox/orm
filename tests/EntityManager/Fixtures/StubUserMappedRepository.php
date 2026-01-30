<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\EntityManager\Fixtures;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\IdentifierInterface;
use Ramsey\Uuid\UuidInterface;

final class StubUserMappedRepository implements EntityRepositoryInterface
{
    public function __construct(
        private readonly UserMappedEntity $entity,
    ) {
    }

    public function persist(EntityInterface $entity): void
    {
        // write path здесь не используется (EntityManager пишет через persister)
    }

    public function remove(EntityInterface $entity): void
    {
        // write path здесь не используется
    }

    public function find(int|UuidInterface|array|IdentifierInterface $id): ?EntityInterface
    {
        return (int) $id === $this->entity->id ? $this->entity : null;
    }

    public function exists(int|UuidInterface|array|IdentifierInterface $id): bool
    {
        return (int) $id === $this->entity->id;
    }

    public function all(): EntityCollection
    {
        return new EntityCollection([$this->entity]);
    }
}
