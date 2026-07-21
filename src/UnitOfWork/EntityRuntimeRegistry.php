<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\UnitOfWork;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use WeakMap;

/**
 * Process-scoped weak registry для поиска runtime-state по экземпляру entity.
 */
final class EntityRuntimeRegistry implements EntityRuntimeRegistryInterface
{
    /** @var WeakMap<EntityInterface, EntityNode> */
    private WeakMap $nodes;

    public function __construct()
    {
        $this->nodes = new WeakMap();
    }

    public function node(EntityInterface $entity): ?EntityNode
    {
        return $this->nodes[$entity] ?? null;
    }

    public function register(EntityInterface $entity, EntityNode $node): void
    {
        $registered = $this->node($entity);
        if ($registered !== null && $registered !== $node) {
            throw new LogicException('Entity instance is already owned by another UnitOfWork.');
        }

        $this->nodes[$entity] = $node;
    }

    public function remove(EntityInterface $entity, EntityNode $node): void
    {
        if ($this->node($entity) === $node) {
            unset($this->nodes[$entity]);
        }
    }
}
