<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Exception\CompositePrimaryKeyNotSupportedException;
use PhpSoftBox\Orm\Metadata\ClassMetadata;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;

use function count;
use function is_object;
use function method_exists;

use const DATE_ATOM;

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
            $filtered[$col->column] = $data[$col->column] ?? null;
        }

        $this->connection
            ->query()
            ->insert($meta->table, $filtered)
            ->execute();
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
            $filtered[$col->column] = $data[$col->column] ?? null;
        }

        $this->connection
            ->query()
            ->update($meta->table, $filtered)
            ->where($pk . ' = :__orm_pk', ['__orm_pk' => $idValue])
            ->execute();
    }

    public function delete(EntityInterface $entity): void
    {
        $meta = $this->metadata->for($entity::class);

        $pk = $this->primaryKeyColumn($meta);

        $id = $entity->id();
        if ($id === null) {
            throw new InvalidArgumentException('Cannot delete entity without id().');
        }

        $idValue = is_object($id) && method_exists($id, 'toString') ? $id->toString() : $id;

        if ($meta->softDelete !== null) {
            $this->connection
                ->query()
                ->update($meta->table, [
                    $meta->softDelete->column => new DateTimeImmutable()->format(DATE_ATOM),
                ])
                ->where($pk . ' = :__orm_pk', ['__orm_pk' => $idValue])
                ->execute();

            return;
        }

        $this->connection
            ->query()
            ->delete($meta->table)
            ->where($pk . ' = :__orm_pk', ['__orm_pk' => $idValue])
            ->execute();
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
}
