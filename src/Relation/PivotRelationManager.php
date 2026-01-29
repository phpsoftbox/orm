<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Менеджер для управления many-to-many связями через pivot-таблицу.
 *
 * Сценарии:
 * - $em->pivot($user, 'roles')->attach($roleId)
 * - $em->pivot($user, 'roles')->detach($roleId)
 * - $em->pivot($user, 'roles')->sync([$roleId1, $roleId2])
 */
final readonly class PivotRelationManager
{
    /**
     * @param EntityInterface $owner Сущность, на которой объявлена связь ("parent" сторона).
     * @param non-empty-string $relationProperty Имя свойства связи (как в метадате).
     */
    public function __construct(
        private PivotRelationWriter $writer,
        private EntityInterface $owner,
        private string $relationProperty,
    ) {
    }

    /**
     * Добавляет связь в pivot-таблицу.
     *
     * @param int|string|UuidInterface $relatedId
     * @param array<string, mixed> $pivotData Дополнительные поля для pivot-таблицы.
     */
    public function attach(int|string|UuidInterface $relatedId, array $pivotData = []): void
    {
        $this->writer->attach($this->owner, $this->relationProperty, $relatedId, $pivotData);
    }

    /**
     * Удаляет связь из pivot-таблицы.
     *
     * @param int|string|UuidInterface $relatedId
     */
    public function detach(int|string|UuidInterface $relatedId): void
    {
        $this->writer->detach($this->owner, $this->relationProperty, $relatedId);
    }

    /**
     * Синхронизирует pivot-таблицу: приводит связи к точному списку.
     *
     * @param list<int|string|UuidInterface> $relatedIds
     */
    public function sync(array $relatedIds): void
    {
        $this->writer->sync($this->owner, $this->relationProperty, $relatedIds);
    }

    /**
     * Синхронизирует pivot-таблицу по карте relatedId => pivotData.
     *
     * @param array<int|string|\Ramsey\Uuid\UuidInterface, array<string, mixed>> $relatedIdToPivotData
     */
    public function syncWithPivotData(array $relatedIdToPivotData, bool $updatePivot = false): void
    {
        $this->writer->syncWithPivotData(
            owner: $this->owner,
            relationProperty: $this->relationProperty,
            relatedIdToPivotData: $relatedIdToPivotData,
            updatePivot: $updatePivot,
        );
    }
}
