<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\UnitOfWork;

use PhpSoftBox\Orm\Contracts\EntityHeapInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use PhpSoftBox\Orm\Exception\EntityIdentityCollisionException;
use PhpSoftBox\Orm\Identity\EntityKey;
use Ramsey\Uuid\UuidInterface;
use Throwable;
use WeakMap;
use WeakReference;

/**
 * Внешний runtime-граф ORM: node по экземпляру и weak identity index по class + id.
 */
final class EntityHeap implements EntityHeapInterface
{
    /** @var WeakMap<EntityInterface, EntityNode> */
    private WeakMap $nodes;

    /** @var array<string, WeakReference<EntityInterface>> */
    private array $identityIndex = [];

    public function __construct(
        private readonly EntityRuntimeRegistryInterface $runtimeRegistry = new EntityRuntimeRegistry(),
    ) {
        $this->nodes = new WeakMap();
    }

    public function runtimeRegistry(): EntityRuntimeRegistryInterface
    {
        return $this->runtimeRegistry;
    }

    public function node(EntityInterface $entity): ?EntityNode
    {
        return $this->nodes[$entity] ?? null;
    }

    public function getOrCreateNode(EntityInterface $entity, ?EntityState $state = null): EntityNode
    {
        $node = $this->node($entity);
        if ($node !== null) {
            if ($state !== null) {
                $node->setState($state);
            }

            $this->synchronizeIdentity($entity, $node);

            return $node;
        }

        $node = new EntityNode($state);

        $this->nodes[$entity] = $node;
        try {
            $this->runtimeRegistry->register($entity, $node);
            $this->synchronizeIdentity($entity, $node);
        } catch (Throwable $exception) {
            $this->remove($entity);

            throw $exception;
        }

        return $node;
    }

    public function find(string $entityClass, int|string|UuidInterface $id): ?EntityInterface
    {
        $key       = EntityKey::fromParts($entityClass, $id)->toString();
        $reference = $this->identityIndex[$key] ?? null;
        $entity    = $reference?->get();

        if (!$entity instanceof EntityInterface) {
            unset($this->identityIndex[$key]);

            return null;
        }

        return $entity;
    }

    public function remove(EntityInterface $entity): void
    {
        $node = $this->node($entity);
        $key  = $node?->identityKey();
        if ($key !== null && ($this->identityIndex[$key] ?? null)?->get() === $entity) {
            unset($this->identityIndex[$key]);
        }

        if ($node !== null) {
            $this->runtimeRegistry->remove($entity, $node);
            unset($this->nodes[$entity]);
        }
    }

    public function clear(): void
    {
        foreach ($this->nodes as $entity => $node) {
            $this->runtimeRegistry->remove($entity, $node);
        }

        $this->nodes = new WeakMap();

        $this->identityIndex = [];
    }

    private function synchronizeIdentity(EntityInterface $entity, EntityNode $node): void
    {
        $id  = $entity->id();
        $key = $id === null ? null : EntityKey::fromParts($entity::class, $id)->toString();

        if ($node->identityKey() === $key) {
            return;
        }

        if ($key !== null) {
            $managed = $this->find($entity::class, $id);
            if ($managed !== null && $managed !== $entity) {
                throw new EntityIdentityCollisionException(
                    'Another instance of ' . $entity::class . ' with id "' . $id . '" is already managed.',
                );
            }
        }

        $previousKey = $node->identityKey();
        if ($previousKey !== null && ($this->identityIndex[$previousKey] ?? null)?->get() === $entity) {
            unset($this->identityIndex[$previousKey]);
        }

        $node->setIdentityKey($key);
        if ($key !== null) {
            $this->identityIndex[$key] = WeakReference::create($entity);
        }
    }
}
