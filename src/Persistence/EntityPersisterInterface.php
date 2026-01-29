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

    public function delete(EntityInterface $entity): void;

    /**
     * Физически удаляет запись из БД (hard delete), игнорируя soft delete behavior.
     */
    public function forceDelete(EntityInterface $entity): void;
}
