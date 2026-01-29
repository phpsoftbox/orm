<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Repository;

use InvalidArgumentException;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\IdentifierInterface;
use PhpSoftBox\Orm\Exception\CompositePrimaryKeyNotSupportedException;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Persistence\DefaultEntityPersister;
use PhpSoftBox\Orm\Persistence\EntityPersisterInterface;
use PhpSoftBox\Orm\TypeCasting\DefaultTypeCasterFactory;
use PhpSoftBox\Orm\TypeCasting\Options\TypeCastOptionsManager;
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
final class GenericEntityRepository implements EntityRepositoryInterface
{
    private readonly MetadataProviderInterface $metadata;

    private readonly AutoEntityMapper $mapper;

    private readonly EntityPersisterInterface $persister;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $entityClass,
    ) {
        $this->metadata = new AttributeMetadataProvider();

        $this->mapper = new AutoEntityMapper(
            metadata: $this->metadata,
            typeCaster: new DefaultTypeCasterFactory()->create(),
            optionsManager: new TypeCastOptionsManager(),
        );

        $this->persister = new DefaultEntityPersister(
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
     * @throws ReflectionException
     */
    private function findInternal(int|UuidInterface|array|IdentifierInterface $id, bool $includeDeleted): ?EntityInterface
    {
        [$meta, $pkColumn] = $this->resolveMetaAndPkColumn($id);

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
     * @return array{0: \PhpSoftBox\Orm\Metadata\ClassMetadata, 1: string}
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
    private function resolvePrimaryKeyColumn(\PhpSoftBox\Orm\Metadata\ClassMetadata $meta): array
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
