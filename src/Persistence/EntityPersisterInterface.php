<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Persistence;

use PhpSoftBox\Orm\Contracts\EntityInterface;

interface EntityPersisterInterface
{
    /**
     * @param array<string, mixed>|null $dataOverride
     */
    public function insert(EntityInterface $entity, ?array $dataOverride = null): void;

    /**
     * @param array<string, mixed>|null $dataOverride
     */
    public function update(EntityInterface $entity, ?array $dataOverride = null): void;

    /**
     * Удаляет запись: soft delete (UPDATE) или физический DELETE.
     *
     * $additionalColumns — колонки, которые слушатели `OnDelete` добавили в состояние; они попадают
     * в тот же UPDATE, что и колонка soft delete. При физическом удалении игнорируются.
     *
     * @param array<string, mixed> $additionalColumns
     */
    public function delete(EntityInterface $entity, array $additionalColumns = []): void;

    /**
     * Восстанавливает soft-deleted запись.
     *
     * @param array<string, mixed>|null $dataOverride
     */
    public function restore(EntityInterface $entity, ?array $dataOverride = null): void;

    /**
     * Физически удаляет запись из БД (hard delete), игнорируя soft delete behavior.
     */
    public function forceDelete(EntityInterface $entity): void;
}
