<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Orm\UnitOfWork\EntityNode;
use PhpSoftBox\Orm\UnitOfWork\EntityState;
use Ramsey\Uuid\UuidInterface;

interface EntityHeapInterface
{
    public function runtimeRegistry(): EntityRuntimeRegistryInterface;

    public function node(EntityInterface $entity): ?EntityNode;

    public function getOrCreateNode(EntityInterface $entity, ?EntityState $state = null): EntityNode;

    /**
     * @param class-string $entityClass
     */
    public function find(string $entityClass, int|string|UuidInterface $id): ?EntityInterface;

    public function remove(EntityInterface $entity): void;

    public function clear(): void;
}
