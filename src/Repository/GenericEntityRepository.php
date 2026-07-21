<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Repository;

use InvalidArgumentException;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Contracts\WarmupAwareConnectionInterface;
use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;
use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\BulkEntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\IdentifierInterface;
use PhpSoftBox\Orm\Contracts\SoftDeleteAwareEntityRepositoryInterface;
use PhpSoftBox\Orm\Exception\CompositePrimaryKeyNotSupportedException;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\ClassMetadata;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use Ramsey\Uuid\UuidInterface;
use ReflectionException;

use function count;
use function is_array;

/**
 * Generic entity repository.
 *
 * Используется как fallback, когда для сущности не найден кастомный репозиторий.
 *
 * На текущем этапе:
 * - поддерживает EntityInterface + атрибуты метаданных (#[Entity], #[Id], #[Column])
 * - выполняет persist/remove через EntityPersisterInterface
 */
final class GenericEntityRepository implements SoftDeleteAwareEntityRepositoryInterface, BulkEntityRepositoryInterface
{
    private readonly MetadataProviderInterface $metadata;

    private readonly AutoEntityMapper $mapper;

    private readonly EntityPersisterInterface $persister;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $entityClass,
        ?MetadataProviderInterface $metadata = null,
        ?AutoEntityMapper $mapper = null,
        ?EntityPersisterInterface $persister = null,
    ) {
        $this->metadata = $metadata ?? new AttributeMetadataProvider();

        $this->mapper = $mapper ?? new AutoEntityMapper(
            metadata: $this->metadata,
            typeCaster: new DefaultTypeCasterFactory()->create(),
            optionsManager: new TypeCastOptionsManager(),
        );

        $this->persister = $persister ?? new DefaultEntityPersister(
            connection: $this->connection,
            metadata: $this->metadata,
            mapper: $this->mapper,
        );
    }

    public function persist(EntityInterface $entity): void
    {
        $entity->id() === null
            ? $this->persister->insert($entity)
            : $this->persister->update($entity);
    }

    public function remove(EntityInterface $entity): void
    {
        $this->persister->delete($entity);
    }

    public function findWithDeleted(int|UuidInterface|array|IdentifierInterface $id): ?EntityInterface
    {
        return $this->findInternal($id, includeDeleted: true);
    }

    public function find(int|UuidInterface|array|IdentifierInterface $id): ?EntityInterface
    {
        return $this->findInternal($id, includeDeleted: false);
    }

    public function exists(int|UuidInterface|array|IdentifierInterface $id): bool
    {
        return $this->existsInternal($id, includeDeleted: false);
    }

    public function existsWithDeleted(int|UuidInterface|array|IdentifierInterface $id): bool
    {
        return $this->existsInternal($id, includeDeleted: true);
    }

    /**
     * @param list<int|string> $ids
     */
    public function findManyByColumn(array $ids, string $column = 'id', bool $withDeleted = false): EntityCollection
    {
        if ($ids === []) {
            return new EntityCollection([]);
        }

        $meta = $this->metadata->for($this->entityClass);

        return $this->findManyByLookup(
            LookupSpec::forTable($meta->table)->lookupColumn($column)->values($ids),
            withDeleted: $withDeleted,
        );
    }

    public function findManyByLookup(LookupSpec $lookup, bool $withDeleted = false): EntityCollection
    {
        if ($lookup->lookupValues() === []) {
            return new EntityCollection([]);
        }

        $meta = $this->metadata->for($this->entityClass);
        $this->assertLookupTable($lookup, $meta);

        if (($withDeleted || $meta->softDelete === null) && $this->connection instanceof WarmupAwareConnectionInterface) {
            $rows = $this->isPrimaryKeyLookup($lookup, $meta)
                ? $this->connection->warmup()->manyUnique($lookup)
                : $this->connection->warmup()->manyGrouped($lookup);

            return $this->hydrateManyRows($rows);
        }

        return $this->hydrateManyRows($this->fetchRowsByLookup($lookup, $withDeleted, $meta));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRowsByLookup(LookupSpec $lookup, bool $withDeleted, ClassMetadata $meta): array
    {
        $qb = $this->connection
            ->query()
            ->select()
            ->from($lookup->tableName());

        $this->applyLookupCriteria($qb, $lookup->whereCriteria());
        $qb->whereIn($lookup->lookupColumnName(), $lookup->lookupValues());

        if (!$withDeleted && $meta->softDelete !== null) {
            $qb->where($meta->softDelete->column . ' IS NULL');
        }

        return $qb->fetchAll();
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function hydrateManyRows(array $rows): EntityCollection
    {
        $items = [];
        foreach ($rows as $row) {
            /** @var EntityInterface $entity */
            $entity  = $this->mapper->hydrate($this->entityClass, $row);
            $items[] = $entity;
        }

        return new EntityCollection($items);
    }

    /**
     * Физически удаляет запись из БД (hard delete), игнорируя soft delete behavior.
     */
    public function forceDelete(EntityInterface $entity): void
    {
        $meta = $this->metadata->for($this->entityClass);

        [$pkColumn] = $this->resolvePrimaryKeyColumn($meta);

        $id = $entity->id();
        if ($id === null) {
            throw new InvalidArgumentException('Cannot forceDelete entity without id().');
        }

        $this->connection
            ->query()
            ->delete($meta->table)
            ->where($pkColumn . ' = :__pk', ['__pk' => $id])
            ->execute();
    }

    /**
     * Восстанавливает soft-deleted запись.
     */
    public function restore(EntityInterface $entity): void
    {
        $this->persister->restore($entity);
    }

    /**
     * @throws ReflectionException
     */
    private function findInternal(int|UuidInterface|array|IdentifierInterface $id, bool $includeDeleted): ?EntityInterface
    {
        [$meta, $pkColumn] = $this->resolveMetaAndPkColumn($id);

        if (($includeDeleted || $meta->softDelete === null) && $this->connection instanceof WarmupAwareConnectionInterface) {
            $row = $this->connection->warmup()->one(
                LookupSpec::forTable($meta->table)->lookupColumn($pkColumn)->value($id),
            );
            if ($row === null) {
                return null;
            }

            /** @var EntityInterface $entity */
            $entity = $this->mapper->hydrate($this->entityClass, $row);

            return $entity;
        }

        $qb = $this->connection
            ->query()
            ->select()
            ->from($meta->table)
            ->where($pkColumn . ' = :__pk', ['__pk' => $id]);

        if (!$includeDeleted && $meta->softDelete !== null) {
            $qb->where($meta->softDelete->column . ' IS NULL');
        }

        $row = $qb->limit(1)->fetchOne();

        if ($row === null) {
            return null;
        }

        /** @var EntityInterface $entity */
        $entity = $this->mapper->hydrate($this->entityClass, $row);

        return $entity;
    }

    /**
     * @throws ReflectionException
     */
    private function existsInternal(int|UuidInterface|array|IdentifierInterface $id, bool $includeDeleted): bool
    {
        [$meta, $pkColumn] = $this->resolveMetaAndPkColumn($id);

        if (($includeDeleted || $meta->softDelete === null) && $this->connection instanceof WarmupAwareConnectionInterface) {
            return $this->connection->warmup()->one(
                LookupSpec::forTable($meta->table)->lookupColumn($pkColumn)->value($id),
            ) !== null;
        }

        $qb = $this->connection
            ->query()
            ->select(['1 AS __exists'])
            ->from($meta->table)
            ->where($pkColumn . ' = :__pk', ['__pk' => $id]);

        if (!$includeDeleted && $meta->softDelete !== null) {
            $qb->where($meta->softDelete->column . ' IS NULL');
        }

        $row = $qb->limit(1)->fetchOne();

        return $row !== null;
    }

    /**
     * @param 'default'|'including'|'only_deleted' $mode
     */
    private function allInternal(string $mode): EntityCollection
    {
        $meta = $this->metadata->for($this->entityClass);

        $qb = $this->connection
            ->query()
            ->select()
            ->from($meta->table);

        if ($meta->softDelete !== null) {
            if ($mode === 'default') {
                $qb->where($meta->softDelete->column . ' IS NULL');
            } elseif ($mode === 'only_deleted') {
                $qb->where($meta->softDelete->column . ' IS NOT NULL');
            }
        }

        $rows = $qb->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            /** @var EntityInterface $entity */
            $entity  = $this->mapper->hydrate($this->entityClass, $row);
            $items[] = $entity;
        }

        return new EntityCollection($items);
    }

    /**
     * @return array{0: ClassMetadata, 1: string}
     */
    private function resolveMetaAndPkColumn(int|UuidInterface|array|IdentifierInterface $id): array
    {
        if (is_array($id) || $id instanceof IdentifierInterface) {
            throw new CompositePrimaryKeyNotSupportedException('Composite primary keys are not supported yet.');
        }

        $meta = $this->metadata->for($this->entityClass);

        [$pkColumn] = $this->resolvePrimaryKeyColumn($meta);

        return [$meta, $pkColumn];
    }

    /**
     * @return array{0: string}
     */
    private function resolvePrimaryKeyColumn(ClassMetadata $meta): array
    {
        if (count($meta->pkProperties) !== 1) {
            throw new CompositePrimaryKeyNotSupportedException('Composite primary keys are not supported yet.');
        }

        $pkProperty = $meta->pkProperties[0];
        $pkMeta     = $meta->columns[$pkProperty] ?? null;
        if ($pkMeta === null) {
            throw new InvalidArgumentException('Primary key property is not mapped as Column: ' . $pkProperty);
        }

        return [$pkMeta->column];
    }

    private function assertLookupTable(LookupSpec $lookup, ClassMetadata $meta): void
    {
        if ($lookup->tableName() !== $meta->table) {
            throw new InvalidArgumentException('Lookup table does not match repository table.');
        }
    }

    private function isPrimaryKeyLookup(LookupSpec $lookup, ClassMetadata $meta): bool
    {
        if (count($meta->pkProperties) !== 1) {
            return false;
        }

        $pkProperty = $meta->pkProperties[0];
        $pkMeta     = $meta->columns[$pkProperty] ?? null;
        if ($pkMeta === null) {
            return false;
        }

        return $lookup->lookupColumnName() === $pkMeta->column;
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function applyLookupCriteria(SelectQueryBuilder $query, array $criteria): void
    {
        $index = 0;

        foreach ($criteria as $column => $value) {
            if (is_array($value)) {
                $query->whereIn((string) $column, $value);
                continue;
            }

            if ($value === null) {
                $query->whereNull((string) $column);
                continue;
            }

            $index++;
            $param = '__lookup_' . $index;
            $query->where((string) $column . ' = :' . $param, [$param => $value]);
        }
    }

    public function all(): EntityCollection
    {
        return $this->allInternal(mode: 'default');
    }

    public function allWithDeleted(): EntityCollection
    {
        return $this->allInternal(mode: 'including');
    }

    public function onlyDeleted(): EntityCollection
    {
        return $this->allInternal(mode: 'only_deleted');
    }
}
