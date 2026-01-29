<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Orm\UnitOfWork\EntitySnapshot;
use PhpSoftBox\Orm\UnitOfWork\EntityState;

/**
 * Unit of Work отвечает за отслеживание состояний сущностей.
 *
 * Цель: Entity/Repository не должны сами решать, новая сущность или нет.
 */
interface UnitOfWorkInterface
{
    /**
     * Возвращает текущее состояние сущности.
     */
    public function state(EntityInterface $entity): ?EntityState;

    /**
     * Помечает сущность как новую (будет INSERT).
     */
    public function markNew(EntityInterface $entity): void;

    /**
     * Помечает сущность как существующую/загруженную (будет UPDATE).
     */
    public function markManaged(EntityInterface $entity): void;

    /**
     * Помечает сущность к удалению (будет DELETE).
     */
    public function markRemoved(EntityInterface $entity): void;

    /**
     * Сохраняет снапшот текущего состояния сущности.
     *
     * @param array<string, mixed> $data
     */
    public function takeSnapshot(EntityInterface $entity, array $data): void;

    public function snapshot(EntityInterface $entity): ?EntitySnapshot;

    /**
     * Проверяет, что сущность изменилась относительно последнего снапшота.
     *
     * @param array<string, mixed> $currentData
     */
    public function isDirty(EntityInterface $entity, array $currentData): bool;

    /**
     * Сбрасывает все состояние (например, в конце запроса/транзакции).
     */
    public function clear(): void;

    /**
     * Планирует сохранение сущности.
     *
     * UnitOfWork сам решает, будет ли это INSERT или UPDATE.
     */
    public function schedulePersist(EntityInterface $entity): void;

    /**
     * Планирует удаление сущности.
     */
    public function scheduleRemove(EntityInterface $entity): void;

    /**
     * Планирует физическое удаление сущности (hard delete), игнорируя soft-delete behavior.
     */
    public function scheduleForceRemove(EntityInterface $entity): void;

    /**
     * @return list<EntityInterface>
     */
    public function scheduledInserts(): array;

    /**
     * @return list<EntityInterface>
     */
    public function scheduledUpdates(): array;

    /**
     * @return list<EntityInterface>
     */
    public function scheduledDeletes(): array;

    /**
     * @return list<EntityInterface>
     */
    public function scheduledForceDeletes(): array;

    /**
     * Очищает запланированные операции (не влияет на snapshot/state).
     */
    public function clearScheduledOperations(): void;
}
