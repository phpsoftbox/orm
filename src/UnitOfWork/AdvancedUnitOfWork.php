<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\UnitOfWork;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\IdentityMapInterface;
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
final class AdvancedUnitOfWork implements UnitOfWorkInterface
{
    /**
     * @var array<int, EntityState>
     */
    private array $states = [];

    /**
     * Кэш результатов exists() по EntityKey.
     *
     * @var array<string, bool>
     */
    private array $existsCache = [];

    /**
     * @var array<int, EntitySnapshot>
     */
    private array $snapshots = [];

    /** @var array<int, EntityInterface> */
    private array $scheduledInserts = [];

    /** @var array<int, EntityInterface> */
    private array $scheduledUpdates = [];

    /** @var array<int, EntityInterface> */
    private array $scheduledDeletes = [];

    /** @var array<int, EntityInterface> */
    private array $scheduledForceDeletes = [];

    public function __construct(
        private readonly IdentityMapInterface $identityMap,
    ) {
    }

    public function identityMap(): IdentityMapInterface
    {
        return $this->identityMap;
    }

    public function state(EntityInterface $entity): ?EntityState
    {
        return $this->states[spl_object_id($entity)] ?? null;
    }

    public function markNew(EntityInterface $entity): void
    {
        $this->states[spl_object_id($entity)] = EntityState::New;

        $this->attachToIdentityMapIfPossible($entity);
    }

    public function markManaged(EntityInterface $entity): void
    {
        $this->states[spl_object_id($entity)] = EntityState::Managed;

        $this->attachToIdentityMapIfPossible($entity);
    }

    public function markRemoved(EntityInterface $entity): void
    {
        $this->states[spl_object_id($entity)] = EntityState::Removed;
    }

    public function takeSnapshot(EntityInterface $entity, array $data): void
    {
        $this->snapshots[spl_object_id($entity)] = new EntitySnapshot($this->normalizeSnapshotData($data));
    }

    public function snapshot(EntityInterface $entity): ?EntitySnapshot
    {
        return $this->snapshots[spl_object_id($entity)] ?? null;
    }

    public function isDirty(EntityInterface $entity, array $currentData): bool
    {
        $snapshot = $this->snapshot($entity);
        if ($snapshot === null) {
            return true;
        }

        return $snapshot->data !== $this->normalizeSnapshotData($currentData);
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
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid], $this->scheduledDeletes[$oid], $this->scheduledForceDeletes[$oid]);

            return;
        }

        $state = $this->state($entity) ?? ($entity->id() === null ? EntityState::New : EntityState::Managed);

        if ($state === EntityState::New) {
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid]);

            return;
        }

        unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid]);
        $this->scheduledDeletes[$oid] = $entity;
    }

    public function scheduleForceRemove(EntityInterface $entity): void
    {
        $oid = spl_object_id($entity);

        if (isset($this->scheduledInserts[$oid])) {
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid], $this->scheduledDeletes[$oid], $this->scheduledForceDeletes[$oid]);

            return;
        }

        unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid], $this->scheduledDeletes[$oid]);
        $this->scheduledForceDeletes[$oid] = $entity;
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

    public function clearScheduledOperations(): void
    {
        $this->scheduledInserts      = [];
        $this->scheduledUpdates      = [];
        $this->scheduledDeletes      = [];
        $this->scheduledForceDeletes = [];
    }

    public function clear(): void
    {
        $this->states      = [];
        $this->existsCache = [];
        $this->snapshots   = [];
        $this->identityMap->clear();
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

        $cached = $this->identityMap->get($key);
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

    private function attachToIdentityMapIfPossible(EntityInterface $entity): void
    {
        $id = $entity->id();
        if ($id === null) {
            return;
        }

        $key = EntityKey::fromParts($entity::class, $id);
        $this->identityMap->set($key, $entity);
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
