<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Exception\CompositePrimaryKeyNotSupportedException;
use PhpSoftBox\Orm\Exception\EntityPersistException;
use PhpSoftBox\Orm\Metadata\ClassMetadata;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use ReflectionObject;

use function array_key_exists;
use function count;
use function in_array;
use function is_object;
use function method_exists;
use function property_exists;

/**
 * Persister по умолчанию.
 *
 * На текущем этапе:
 * - работает только с single primary key
 * - использует DBAL QueryBuilder (ConnectionInterface::query())
 */
final readonly class DefaultEntityPersister implements EntityPersisterInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private MetadataProviderInterface $metadata,
        private AutoEntityMapper $mapper,
    ) {
    }

    public function insert(EntityInterface $entity, ?array $dataOverride = null): void
    {
        $meta = $this->metadata->for($entity::class);

        $data = $dataOverride ?? $this->mapper->extract($entity);

        // insertable columns
        $filtered = [];
        foreach ($meta->insertableColumns() as $col) {
            if (!array_key_exists($col->column, $data)) {
                continue;
            }

            if ($col->isId && $meta->idGenerationStrategy === 'auto' && $data[$col->column] === null) {
                continue;
            }

            $filtered[$col->column] = $data[$col->column];
        }

        $this->connection
            ->query()
            ->insert($meta->table, $filtered)
            ->execute();

        $this->assignGeneratedId($entity, $meta);

        if ($meta->idGenerationStrategy === 'auto' && count($meta->pkProperties) === 1 && $entity->id() === null) {
            throw EntityPersistException::missingGeneratedId($entity::class, $meta->table);
        }
    }

    public function update(EntityInterface $entity, ?array $dataOverride = null): void
    {
        $meta = $this->metadata->for($entity::class);

        $pk = $this->primaryKeyColumn($meta);

        $id = $entity->id();
        if ($id === null) {
            throw new InvalidArgumentException('Cannot update entity without id().');
        }

        $idValue = is_object($id) && method_exists($id, 'toString') ? $id->toString() : $id;

        $data = $dataOverride ?? $this->mapper->extract($entity);

        $filtered = [];
        foreach ($meta->updatableColumns() as $col) {
            // не обновляем pk
            if ($col->column === $pk) {
                continue;
            }
            if (!array_key_exists($col->column, $data)) {
                continue;
            }

            $filtered[$col->column] = $data[$col->column];
        }

        $this->connection
            ->query()
            ->update($meta->table, $filtered)
            ->where($pk . ' = :__orm_pk', ['__orm_pk' => $idValue])
            ->execute();
    }

    public function delete(EntityInterface $entity): void
    {
        $connection = $this->connection;

        $meta = $this->metadata->for($entity::class);
        $pk   = $this->primaryKeyColumn($meta);

        $id = $entity->id();
        if ($id === null) {
            throw new InvalidArgumentException('Cannot delete entity without id().');
        }

        $idValue = is_object($id) && method_exists($id, 'toString') ? $id->toString() : $id;

        if ($meta->softDelete !== null) {
            $deletedValue = new DateTimeImmutable()->format('Y-m-d H:i:s');

            $connection
                ->query()
                ->update($meta->table, [
                    $meta->softDelete->column => $deletedValue,
                ])
                ->where($pk . ' = :__orm_pk', ['__orm_pk' => $idValue])
                ->execute();

            $column = $meta->columns[$meta->softDelete->entityField] ?? null;
            $this->setEntityProperty(
                $entity,
                $meta->softDelete->entityField,
                $column !== null ? $this->mapper->castFromMetadata($column, $deletedValue) : $deletedValue,
            );

            return;
        }

        $connection
            ->query()
            ->delete($meta->table)
            ->where($pk . ' = :__orm_pk', ['__orm_pk' => $idValue])
            ->execute();
    }

    public function restore(EntityInterface $entity, ?array $dataOverride = null): void
    {
        $meta = $this->metadata->for($entity::class);

        if ($meta->softDelete === null) {
            throw new InvalidArgumentException('Cannot restore entity without SoftDelete behavior.');
        }

        $pk = $this->primaryKeyColumn($meta);

        $id = $entity->id();
        if ($id === null) {
            throw new InvalidArgumentException('Cannot restore entity without id().');
        }

        $idValue = is_object($id) && method_exists($id, 'toString') ? $id->toString() : $id;

        $deletedValue = $dataOverride[$meta->softDelete->column] ?? null;

        $this->connection
            ->query()
            ->update($meta->table, [
                $meta->softDelete->column => $deletedValue,
            ])
            ->where($pk . ' = :__orm_pk', ['__orm_pk' => $idValue])
            ->execute();

        $this->setEntityProperty($entity, $meta->softDelete->entityField, $deletedValue);
    }

    public function forceDelete(EntityInterface $entity): void
    {
        $meta = $this->metadata->for($entity::class);

        $pk = $this->primaryKeyColumn($meta);

        $id = $entity->id();
        if ($id === null) {
            throw new InvalidArgumentException('Cannot forceDelete entity without id().');
        }

        $idValue = is_object($id) && method_exists($id, 'toString') ? $id->toString() : $id;

        $this->connection
            ->query()
            ->delete($meta->table)
            ->where($pk . ' = :__orm_pk', ['__orm_pk' => $idValue])
            ->execute();
    }

    private function primaryKeyColumn(ClassMetadata $meta): string
    {
        if (count($meta->pkProperties) !== 1) {
            throw new CompositePrimaryKeyNotSupportedException('Composite primary keys are not supported yet.');
        }

        $pkProperty = $meta->pkProperties[0];
        $pkMeta     = $meta->columns[$pkProperty] ?? null;
        if ($pkMeta === null) {
            throw new InvalidArgumentException('Primary key property is not mapped as Column: ' . $pkProperty);
        }

        return $pkMeta->column;
    }

    private function assignGeneratedId(EntityInterface $entity, ClassMetadata $meta): void
    {
        if ($meta->idGenerationStrategy !== 'auto') {
            return;
        }

        if (count($meta->pkProperties) !== 1) {
            return;
        }

        if ($entity->id() !== null) {
            return;
        }

        $pkProperty = $meta->pkProperties[0];
        $pkMeta     = $meta->columns[$pkProperty] ?? null;
        if ($pkMeta === null) {
            return;
        }

        $lastId = $this->connection->pdo()->lastInsertId();
        if ($lastId === '') {
            return;
        }

        $value = $lastId;
        if (in_array($pkMeta->type, ['int', 'integer', 'bigint', 'bigInteger'], true)) {
            $value = (int) $lastId;
        }

        $this->setEntityProperty($entity, $pkProperty, $value);
    }

    private function setEntityProperty(EntityInterface $entity, string $property, mixed $value): void
    {
        if (!property_exists($entity, $property)) {
            return;
        }

        $ref = new ReflectionObject($entity);

        if (!$ref->hasProperty($property)) {
            return;
        }

        $prop = $ref->getProperty($property);
        if (!$prop->isPublic()) {
            $prop->setAccessible(true);
        }

        $prop->setValue($entity, $value);
    }
}
