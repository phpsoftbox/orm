<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Relation;

use InvalidArgumentException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\EntityManager;
use PhpSoftBox\Orm\Metadata\RelationMetadata;
use Ramsey\Uuid\UuidInterface;

use function array_merge;
use function is_scalar;

/**
 * Низкоуровневый writer для many-to-many связей через pivot-таблицу.
 *
 * Важно:
 * - writer пишет напрямую в БД (вне UnitOfWork),
 * - поэтому после вызовов может понадобиться reload relations (например $em->load(...)) или refresh().
 */
final readonly class PivotRelationWriter
{
    public function __construct(
        private EntityManager
    $em)
    {
    }

    /**
     * Добавляет связь в pivot-таблицу.
     *
     * @param array<string, mixed> $pivotData
     */
    public function attach(EntityInterface $owner, string $relationProperty, int|string|UuidInterface $relatedId, array $pivotData = []): void
    {
        $relation = $this->belongsToManyMeta($owner, $relationProperty);

        $ownerId = $owner->id();
        if ($ownerId === null) {
            throw new InvalidArgumentException('Cannot attach relation: owner id is null.');
        }

        $ownerId   = $ownerId instanceof UuidInterface ? $ownerId->toString() : $ownerId;
        $relatedId = $relatedId instanceof UuidInterface ? $relatedId->toString() : $relatedId;

        $data = array_merge(
            [
                $relation->foreignPivotKey => $ownerId,
                $relation->relatedPivotKey => $relatedId,
            ],
            $pivotData,
        );

        $this->em
            ->connection()
            ->query()
            ->insert(table: $relation->pivotTable)
            ->values($data)
            ->execute();
    }

    /**
     * Удаляет связь из pivot-таблицы.
     */
    public function detach(EntityInterface $owner, string $relationProperty, int|string|UuidInterface $relatedId): void
    {
        $relation = $this->belongsToManyMeta($owner, $relationProperty);

        $ownerId = $owner->id();
        if ($ownerId === null) {
            throw new InvalidArgumentException('Cannot detach relation: owner id is null.');
        }

        $ownerId   = $ownerId instanceof UuidInterface ? $ownerId->toString() : $ownerId;
        $relatedId = $relatedId instanceof UuidInterface ? $relatedId->toString() : $relatedId;

        $this->em
            ->connection()
            ->query()
            ->delete(table: $relation->pivotTable)
            ->where($relation->foreignPivotKey . ' = :__psb_owner', ['__psb_owner' => $ownerId])
            ->where($relation->relatedPivotKey . ' = :__psb_related', ['__psb_related' => $relatedId])
            ->execute();
    }

    /**
     * Синхронизирует pivot-таблицу: приводит связи к точному списку.
     *
     * @param list<int|string|UuidInterface> $relatedIds
     */
    public function sync(EntityInterface $owner, string $relationProperty, array $relatedIds): void
    {
        $relation = $this->belongsToManyMeta($owner, $relationProperty);

        $ownerId = $owner->id();
        if ($ownerId === null) {
            throw new InvalidArgumentException('Cannot sync relation: owner id is null.');
        }

        $ownerId = $ownerId instanceof UuidInterface ? $ownerId->toString() : $ownerId;

        $normalized = [];
        foreach ($relatedIds as $rid) {
            $rid = $rid instanceof UuidInterface ? $rid->toString() : $rid;
            if (!is_scalar($rid)) {
                continue;
            }
            $normalized[(string) $rid] = $rid;
        }

        $rows = $this->em
            ->connection()
            ->query()
            ->select([$relation->relatedPivotKey])
            ->from($relation->pivotTable)
            ->where($relation->foreignPivotKey . ' = :__psb_owner', ['__psb_owner' => $ownerId])
            ->fetchAll();

        $existing = [];
        foreach ($rows as $row) {
            $rid = $row[$relation->relatedPivotKey] ?? null;
            if ($rid !== null && is_scalar($rid)) {
                $existing[(string) $rid] = $rid;
            }
        }

        foreach ($existing as $ridKey => $ridVal) {
            if (!isset($normalized[$ridKey])) {
                $this->detach($owner, $relationProperty, $ridVal);
            }
        }

        foreach ($normalized as $ridKey => $ridVal) {
            if (!isset($existing[$ridKey])) {
                $this->attach($owner, $relationProperty, $ridVal);
            }
        }
    }

    /**
     * Синхронизирует pivot-таблицу по карте relatedId => pivotData.
     *
     * Правила:
     * - отсутствующие в списке связи удаляются,
     * - отсутствующие в БД связи добавляются (INSERT) с pivotData,
     * - существующие связи по умолчанию не трогаются,
     *   но при $updatePivot=true выполняется UPDATE полей из pivotData.
     *
     * @param array<int|string|UuidInterface, array<string, mixed>> $relatedIdToPivotData
     */
    public function syncWithPivotData(
        EntityInterface $owner,
        string $relationProperty,
        array $relatedIdToPivotData,
        bool $updatePivot = false,
    ): void {
        $relation = $this->belongsToManyMeta($owner, $relationProperty);

        $ownerId = $owner->id();
        if ($ownerId === null) {
            throw new InvalidArgumentException('Cannot sync relation: owner id is null.');
        }

        $ownerId = $ownerId instanceof UuidInterface ? $ownerId->toString() : $ownerId;

        /** @var array<string, array{relatedId: int|string, pivotData: array<string, mixed>}> $desired */
        $desired = [];

        foreach ($relatedIdToPivotData as $rid => $pivotData) {
            $ridValue = $rid instanceof UuidInterface ? $rid->toString() : $rid;

            // ключи в массиве могут приходить как int|string, но также допускаем UuidInterface в значении.
            if ($ridValue instanceof UuidInterface) {
                $ridValue = $ridValue->toString();
            }

            if (!is_scalar($ridValue)) {
                continue;
            }

            $desired[(string) $ridValue] = [
                'relatedId' => $ridValue,
                'pivotData' => $pivotData,
            ];
        }

        $rows = $this->em
            ->connection()
            ->query()
            ->select([$relation->relatedPivotKey])
            ->from($relation->pivotTable)
            ->where($relation->foreignPivotKey . ' = :__psb_owner', ['__psb_owner' => $ownerId])
            ->fetchAll();

        /** @var array<string, int|string> $existing */
        $existing = [];
        foreach ($rows as $row) {
            $rid = $row[$relation->relatedPivotKey] ?? null;
            if ($rid !== null && is_scalar($rid)) {
                $existing[(string) $rid] = $rid;
            }
        }

        // 1) detach лишнее
        foreach ($existing as $ridKey => $ridVal) {
            if (!isset($desired[$ridKey])) {
                $this->detach($owner, $relationProperty, $ridVal);
            }
        }

        // 2) attach недостающее
        foreach ($desired as $ridKey => $spec) {
            if (!isset($existing[$ridKey])) {
                $this->attach($owner, $relationProperty, $spec['relatedId'], $spec['pivotData']);
            }
        }

        // 3) опционально обновляем pivotData для существующих
        if ($updatePivot) {
            foreach ($desired as $ridKey => $spec) {
                if (!isset($existing[$ridKey])) {
                    continue;
                }

                $pivotData = $spec['pivotData'];
                if ($pivotData === []) {
                    continue;
                }

                $this->em
                    ->connection()
                    ->query()
                    ->update(table: $relation->pivotTable)
                    ->set($pivotData)
                    ->where($relation->foreignPivotKey . ' = :__psb_owner', ['__psb_owner' => $ownerId])
                    ->where($relation->relatedPivotKey . ' = :__psb_related', ['__psb_related' => $spec['relatedId']])
                    ->execute();
            }
        }
    }

    private function belongsToManyMeta(EntityInterface $owner, string $relationProperty): RelationMetadata
    {
        $meta     = $this->em->metadataProvider()->for($owner::class);
        $relation = $meta->relations[$relationProperty] ?? null;

        if (!$relation instanceof RelationMetadata || $relation->type !== 'belongs_to_many') {
            throw new InvalidArgumentException('Relation "' . $relationProperty . '" is not BelongsToMany for entity ' . $owner::class);
        }

        if ($relation->pivotTable === null || $relation->foreignPivotKey === null || $relation->relatedPivotKey === null) {
            throw new InvalidArgumentException('BelongsToMany relation is not fully configured (pivotTable/pivot keys).');
        }

        return $relation;
    }
}
