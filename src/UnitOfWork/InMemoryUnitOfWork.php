<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\UnitOfWork;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\UnitOfWorkInterface;

use function spl_object_id;

/**
 * Простейшая реализация Unit of Work.
 *
 * Хранит:
 * - состояние сущности (New/Managed/Removed)
 * - снапшот данных для dirty-checking
 */
final class InMemoryUnitOfWork implements UnitOfWorkInterface
{
    /**
     * @var array<int, EntityState>
     */
    private array $states = [];

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

    public function state(EntityInterface $entity): ?EntityState
    {
        return $this->states[spl_object_id($entity)] ?? null;
    }

    public function markNew(EntityInterface $entity): void
    {
        $this->states[spl_object_id($entity)] = EntityState::New;
    }

    public function markManaged(EntityInterface $entity): void
    {
        $this->states[spl_object_id($entity)] = EntityState::Managed;
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

        // Если ранее планировалось удаление: persist() отменяет delete (сущность остаётся)
        unset($this->scheduledDeletes[$oid]);

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

        // hard delete всегда приоритетнее, но обычный remove его не отменяет
        if (isset($this->scheduledForceDeletes[$oid])) {
            return;
        }

        // Если сущность была запланирована на INSERT, то remove до flush = no-op.
        if (isset($this->scheduledInserts[$oid])) {
            unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid], $this->scheduledDeletes[$oid]);
            return;
        }

        $state = $this->state($entity) ?? ($entity->id() === null ? EntityState::New : EntityState::Managed);

        // Если сущность ещё не была вставлена в БД (NEW) и мы её удаляем до flush,
        // то это no-op: снимаем insert/update и не добавляем delete.
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

        // NEW + forceRemove до flush = no-op
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
        $this->scheduledInserts = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletes = [];
        $this->scheduledForceDeletes = [];
    }

    public function clear(): void
    {
        $this->states = [];
        $this->snapshots = [];
        $this->clearScheduledOperations();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeSnapshotData(array $data): array
    {
        // Минимальная нормализация: стабильный порядок ключей.
        ksort($data);
        return $data;
    }
}
