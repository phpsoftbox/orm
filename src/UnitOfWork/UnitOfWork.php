<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\UnitOfWork;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\EntityRuntimeRegistryInterface;
use PhpSoftBox\Orm\Contracts\UnitOfWorkInterface;
use PhpSoftBox\Orm\Identity\EntityKey;
use Ramsey\Uuid\UuidInterface;

use function array_values;
use function is_array;
use function is_object;
use function ksort;
use function method_exists;
use function spl_object_id;

/**
 * UnitOfWork, который:
 * - хранит состояния сущностей,
 * - поддерживает IdentityMap для 1st-level cache,
 * - при persist() может определять состояние через exists() в репозитории,
 * - поддерживает dirty-checking через снапшоты.
 */
final class UnitOfWork implements UnitOfWorkInterface
{
    /**
     * Кэш результатов exists() по EntityKey.
     *
     * @var array<string, bool>
     */
    private array $existsCache = [];

    /** @var array<int, EntityInterface> */
    private array $scheduledInserts = [];

    /** @var array<int, EntityInterface> */
    private array $scheduledUpdates = [];

    /** @var array<int, EntityInterface> */
    private array $scheduledDeletes = [];

    /** @var array<int, EntityInterface> */
    private array $scheduledForceDeletes = [];

    /** @var array<int, EntityInterface> */
    private array $scheduledRestores = [];

    public function __construct(
        private readonly EntityHeap $heap = new EntityHeap(),
    ) {
    }

    public function heap(): EntityHeap
    {
        return $this->heap;
    }

    public function runtimeRegistry(): EntityRuntimeRegistryInterface
    {
        return $this->heap->runtimeRegistry();
    }

    public function findManaged(string $entityClass, int|string|UuidInterface $id): ?EntityInterface
    {
        return $this->heap->find($entityClass, $id);
    }

    public function state(EntityInterface $entity): ?EntityState
    {
        return $this->heap->node($entity)?->state();
    }

    public function markNew(EntityInterface $entity): void
    {
        $this->heap->getOrCreateNode($entity, EntityState::New);
    }

    public function markManaged(EntityInterface $entity): void
    {
        $this->heap->getOrCreateNode($entity, EntityState::Managed);
    }

    public function markRemoved(EntityInterface $entity): void
    {
        $this->heap->getOrCreateNode($entity, EntityState::Removed);
    }

    public function takeSnapshot(EntityInterface $entity, array $data): void
    {
        $this->heap->getOrCreateNode($entity)->setSnapshot(
            new EntitySnapshot($this->normalizeSnapshotData($data)),
        );
    }

    public function snapshot(EntityInterface $entity): ?EntitySnapshot
    {
        return $this->heap->node($entity)?->snapshot();
    }

    public function isDirty(EntityInterface $entity, array $currentData): bool
    {
        $snapshot = $this->snapshot($entity);
        if ($snapshot === null) {
            return true;
        }

        return $snapshot->data !== $this->normalizeSnapshotData($currentData);
    }

    public function isRelationLoaded(EntityInterface $entity, string $relation): bool
    {
        return $this->heap->node($entity)?->isRelationLoaded($relation) ?? false;
    }

    public function markRelationLoaded(EntityInterface $entity, string $relation): void
    {
        $this->heap->getOrCreateNode($entity)->markRelationLoaded($relation);
    }

    public function forgetLoadedRelation(EntityInterface $entity, string $relation): void
    {
        $this->heap->node($entity)?->forgetRelation($relation);
    }

    public function forgetLoadedRelations(EntityInterface $entity): void
    {
        $this->heap->node($entity)?->forgetRelations();
    }

    public function schedulePersist(EntityInterface $entity): void
    {
        $oid = spl_object_id($entity);

        unset($this->scheduledDeletes[$oid], $this->scheduledForceDeletes[$oid]);

        $state = $this->state($entity) ?? ($entity->id() === null ? EntityState::New : EntityState::Managed);

        if ($state === EntityState::New) {
            $this->scheduledInserts[$oid] = $entity;
            unset($this->scheduledUpdates[$oid]);

            return;
        }

        $this->scheduledUpdates[$oid] = $entity;
        unset($this->scheduledInserts[$oid]);
    }

    public function scheduleRemove(EntityInterface $entity): void
    {
        $oid = spl_object_id($entity);

        if (isset($this->scheduledForceDeletes[$oid])) {
            return;
        }

        if (isset($this->scheduledInserts[$oid])) {
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid], $this->scheduledDeletes[$oid], $this->scheduledForceDeletes[$oid], $this->scheduledRestores[$oid]);

            return;
        }

        $state = $this->state($entity) ?? ($entity->id() === null ? EntityState::New : EntityState::Managed);

        if ($state === EntityState::New) {
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid], $this->scheduledRestores[$oid]);

            return;
        }

        unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid], $this->scheduledRestores[$oid]);
        $this->scheduledDeletes[$oid] = $entity;
    }

    public function scheduleForceRemove(EntityInterface $entity): void
    {
        $oid = spl_object_id($entity);

        if (isset($this->scheduledInserts[$oid])) {
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid], $this->scheduledDeletes[$oid], $this->scheduledForceDeletes[$oid], $this->scheduledRestores[$oid]);

            return;
        }

        unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid], $this->scheduledDeletes[$oid], $this->scheduledRestores[$oid]);
        $this->scheduledForceDeletes[$oid] = $entity;
    }

    public function scheduleRestore(EntityInterface $entity): void
    {
        $oid = spl_object_id($entity);

        if (isset($this->scheduledInserts[$oid])) {
            return;
        }

        unset($this->scheduledDeletes[$oid], $this->scheduledForceDeletes[$oid]);
        $this->scheduledRestores[$oid] = $entity;
    }

    public function scheduledInserts(): array
    {
        return array_values($this->scheduledInserts);
    }

    public function scheduledUpdates(): array
    {
        return array_values($this->scheduledUpdates);
    }

    public function scheduledDeletes(): array
    {
        return array_values($this->scheduledDeletes);
    }

    public function scheduledForceDeletes(): array
    {
        return array_values($this->scheduledForceDeletes);
    }

    public function scheduledRestores(): array
    {
        return array_values($this->scheduledRestores);
    }

    public function clearScheduledOperations(): void
    {
        $this->scheduledInserts      = [];
        $this->scheduledUpdates      = [];
        $this->scheduledDeletes      = [];
        $this->scheduledForceDeletes = [];
        $this->scheduledRestores     = [];
    }

    public function clear(): void
    {
        $this->existsCache = [];
        $this->heap->clear();
        $this->clearScheduledOperations();
    }

    /**
     * Определяет состояние для persist() согласно правилам:
     * - id === null => NEW
     * - id !== null => если сущность уже есть в IdentityMap => MANAGED
     * - иначе: repo->exists(id) => MANAGED, иначе NEW
     */
    public function resolveForPersist(EntityInterface $entity, EntityRepositoryInterface $repository): EntityState
    {
        $id = $entity->id();
        if ($id === null) {
            return EntityState::New;
        }

        $key = EntityKey::fromParts($entity::class, $id);

        $cached = $this->heap->find($entity::class, $id);
        if ($cached !== null) {
            return EntityState::Managed;
        }

        $existsKey = $key->toString();

        $exists = $this->existsCache[$existsKey] ?? null;
        if ($exists === null) {
            $exists                        = $repository->exists($id);
            $this->existsCache[$existsKey] = $exists;
        }

        return $exists ? EntityState::Managed : EntityState::New;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeSnapshotData(array $data): array
    {
        ksort($data);

        foreach ($data as $k => $v) {
            $data[$k] = $this->normalizeValue($v);
        }

        return $data;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof UuidInterface) {
            return $value->toString();
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->normalizeValue($v);
            }

            return $value;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            // Для неизвестных объектов оставляем как есть: сравнение будет по ссылке,
            // но это честно отражает невозможность детерминированно сериализовать объект.
            return $value;
        }

        return $value;
    }
}
