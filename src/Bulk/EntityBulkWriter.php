<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Bulk;

use InvalidArgumentException;
use LogicException;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\QueryBuilder\DeleteQueryBuilder;
use PhpSoftBox\Database\QueryBuilder\UpdateQueryBuilder;
use PhpSoftBox\DatabaseLookup\LookupSpec;
use PhpSoftBox\Orm\Behavior\EventDispatcherInterface;
use PhpSoftBox\Orm\Behavior\TimestampColumnsResolver;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\UnitOfWorkInterface;
use PhpSoftBox\Orm\Exception\CompositePrimaryKeyNotSupportedException;
use PhpSoftBox\Orm\Metadata\ClassMetadata;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\Metadata\PropertyMetadata;
use Ramsey\Uuid\UuidInterface;

use function array_chunk;
use function array_key_exists;
use function array_values;
use function count;
use function is_array;
use function is_int;
use function is_object;
use function is_scalar;
use function method_exists;

final readonly class EntityBulkWriter
{
    private const int DEFAULT_CHUNK_SIZE = 1000;

    /**
     * @param class-string $entityClass
     */
    public function __construct(
        private EntityManagerInterface $orm,
        private ConnectionInterface $connection,
        private MetadataProviderInterface $metadata,
        private UnitOfWorkInterface $unitOfWork,
        private EventDispatcherInterface $events,
        private string $entityClass,
        private ?LookupSpec $lookup = null,
        private int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        private TimestampColumnsResolver $timestamps = new TimestampColumnsResolver(),
    ) {
        if ($this->chunkSize < 1) {
            throw new InvalidArgumentException('Bulk chunk size must be greater than zero.');
        }
    }

    public function lookup(LookupSpec $lookup): self
    {
        return new self(
            $this->orm,
            $this->connection,
            $this->metadata,
            $this->unitOfWork,
            $this->events,
            $this->entityClass,
            $lookup,
            $this->chunkSize,
            $this->timestamps,
        );
    }

    /**
     * @param list<int|string|UuidInterface> $ids
     */
    public function ids(array $ids): self
    {
        $meta = $this->metadata->for($this->entityClass);

        return $this->lookup(
            LookupSpec::forTable($meta->table)
                ->lookupColumn($this->primaryKeyColumn($meta))
                ->values($ids),
        );
    }

    public function chunkSize(int $chunkSize): self
    {
        return new self(
            $this->orm,
            $this->connection,
            $this->metadata,
            $this->unitOfWork,
            $this->events,
            $this->entityClass,
            $this->lookup,
            $chunkSize,
            $this->timestamps,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(array $data): BulkWriteResult
    {
        $meta  = $this->metadata->for($this->entityClass);
        $state = new MutableBulkWriteState($this->normalizeUpdateData($meta, $data));

        $timestamp = $this->timestamps->updatedForUpdate($meta, Clock::now());
        if ($timestamp !== null && !array_key_exists($timestamp->column->column, $state->getData())) {
            $state->register($timestamp->column->column, $timestamp->value);
        }

        return $this->executeWrite(
            meta: $meta,
            action: BulkWriteAction::Update,
            state: $state,
            onEventClass: OnBulkUpdate::class,
            afterEventClass: AfterBulkUpdate::class,
        );
    }

    public function remove(): BulkWriteResult
    {
        $meta  = $this->metadata->for($this->entityClass);
        $state = new MutableBulkWriteState([]);

        if ($meta->softDelete !== null) {
            $state->register($meta->softDelete->column, Clock::now()->format('Y-m-d H:i:s'));
        }

        return $this->executeWrite(
            meta: $meta,
            action: BulkWriteAction::Remove,
            state: $state,
            onEventClass: OnBulkRemove::class,
            afterEventClass: AfterBulkRemove::class,
        );
    }

    public function forceRemove(): BulkWriteResult
    {
        return $this->executeWrite(
            meta: $this->metadata->for($this->entityClass),
            action: BulkWriteAction::ForceRemove,
            state: new MutableBulkWriteState([]),
            onEventClass: OnBulkForceRemove::class,
            afterEventClass: AfterBulkForceRemove::class,
        );
    }

    public function restore(): BulkWriteResult
    {
        $meta = $this->metadata->for($this->entityClass);
        if ($meta->softDelete === null) {
            throw new InvalidArgumentException('Cannot bulk restore entity without SoftDelete behavior.');
        }

        $state = new MutableBulkWriteState([
            $meta->softDelete->column => null,
        ]);

        return $this->executeWrite(
            meta: $meta,
            action: BulkWriteAction::Restore,
            state: $state,
            onEventClass: OnBulkRestore::class,
            afterEventClass: AfterBulkRestore::class,
        );
    }

    /**
     * @param class-string<AbstractBulkWriteCommand> $onEventClass
     * @param class-string<AbstractAfterBulkWriteCommand> $afterEventClass
     */
    private function executeWrite(
        ClassMetadata $meta,
        BulkWriteAction $action,
        MutableBulkWriteState $state,
        string $onEventClass,
        string $afterEventClass,
    ): BulkWriteResult {
        $lookup = $this->normalizedLookup($meta);
        $values = $lookup->lookupValues();

        $result = new BulkWriteResult(
            entityClass: $this->entityClass,
            action: $action,
            requestedValues: count($values),
            affectedRows: 0,
            lookupValues: $values,
        );

        if ($values === []) {
            return $result;
        }

        $this->assertNoScheduledOperations();

        /** @var AbstractBulkWriteCommand $onEvent */
        $onEvent = new $onEventClass($this->orm, $this->entityClass, $lookup, $action, $state);

        $this->events->dispatch($onEvent);
        $this->normalizeActionState($meta, $action, $state);

        $affectedRows = 0;
        foreach ($this->chunkLookup($lookup) as $chunk) {
            $affectedRows += match ($action) {
                BulkWriteAction::Update => $this->executeUpdate($meta, $chunk, $state->getData()),
                BulkWriteAction::Remove => $meta->softDelete !== null
                    ? $this->executeUpdate($meta, $chunk, $state->getData())
                    : $this->executeDelete($meta, $chunk),
                BulkWriteAction::ForceRemove => $this->executeDelete($meta, $chunk),
                BulkWriteAction::Restore     => $this->executeUpdate($meta, $chunk, $state->getData()),
            };
        }

        $result = new BulkWriteResult(
            entityClass: $this->entityClass,
            action: $action,
            requestedValues: count($values),
            affectedRows: $affectedRows,
            lookupValues: $values,
        );

        /** @var AbstractAfterBulkWriteCommand $afterEvent */
        $afterEvent = new $afterEventClass($this->orm, $this->entityClass, $lookup, $action, $state, $result);

        $this->events->dispatch($afterEvent);

        $this->unitOfWork->clear();

        return $result;
    }

    /**
     * @return list<LookupSpec>
     */
    private function chunkLookup(LookupSpec $lookup): array
    {
        $values = $lookup->lookupValues();
        if (count($values) <= $this->chunkSize) {
            return [$lookup];
        }

        $chunks = [];
        foreach (array_chunk($values, $this->chunkSize) as $chunk) {
            $chunks[] = $lookup->values($chunk);
        }

        return $chunks;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function executeUpdate(ClassMetadata $meta, LookupSpec $lookup, array $data): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('Bulk update data must not be empty.');
        }

        $query = $this->connection->query()->update($meta->table, $data);
        $this->applyLookupCriteria($query, $lookup);

        return $query->execute();
    }

    private function executeDelete(ClassMetadata $meta, LookupSpec $lookup): int
    {
        $query = $this->connection->query()->delete($meta->table);
        $this->applyLookupCriteria($query, $lookup);

        return $query->execute();
    }

    private function applyLookupCriteria(UpdateQueryBuilder|DeleteQueryBuilder $query, LookupSpec $lookup): void
    {
        $index = 0;

        foreach ($lookup->whereCriteria() as $column => $value) {
            if (is_array($value)) {
                $query->whereIn((string) $column, array_values($value));
                continue;
            }

            if ($value === null) {
                $query->whereNull((string) $column);
                continue;
            }

            $index++;
            $param = '__bulk_' . $index;
            $query->where((string) $column . ' = :' . $param, [$param => $value]);
        }

        $query->whereIn($lookup->lookupColumnName(), $lookup->lookupValues());
    }

    private function normalizedLookup(ClassMetadata $meta): LookupSpec
    {
        $lookup = $this->lookup;
        if ($lookup === null) {
            throw new InvalidArgumentException('Bulk lookup is not configured.');
        }

        if ($lookup->tableName() !== $meta->table) {
            throw new InvalidArgumentException(
                'Bulk lookup table "' . $lookup->tableName() . '" does not match entity table "' . $meta->table . '".',
            );
        }

        return $lookup->values($this->uniqueValues($lookup->lookupValues()));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeUpdateData(ClassMetadata $meta, array $data): array
    {
        if ($data === []) {
            throw new InvalidArgumentException('Bulk update data must not be empty.');
        }

        $normalized = [];
        foreach ($data as $field => $value) {
            $column = $this->resolveWritableColumn($meta, (string) $field);
            if ($column === null) {
                throw new InvalidArgumentException('Column is not updatable for bulk update: ' . (string) $field);
            }

            $normalized[$column->column] = $value;
        }

        return $normalized;
    }

    private function resolveWritableColumn(ClassMetadata $meta, string $field): ?PropertyMetadata
    {
        if (isset($meta->columns[$field])) {
            $column = $meta->columns[$field];

            return $column->updatable && !$column->isId ? $column : null;
        }

        foreach ($meta->columns as $column) {
            if ($column->column !== $field) {
                continue;
            }

            return $column->updatable && !$column->isId ? $column : null;
        }

        return null;
    }

    private function normalizeActionState(
        ClassMetadata $meta,
        BulkWriteAction $action,
        MutableBulkWriteState $state,
    ): void {
        if ($action === BulkWriteAction::Restore) {
            if ($meta->softDelete === null) {
                throw new InvalidArgumentException('Cannot bulk restore entity without SoftDelete behavior.');
            }

            $state->replace([$meta->softDelete->column => null]);
        }

        if ($action === BulkWriteAction::Remove && $meta->softDelete !== null) {
            $state->replace([
                $meta->softDelete->column => $state->getData()[$meta->softDelete->column] ?? Clock::now()->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function assertNoScheduledOperations(): void
    {
        if (
            $this->unitOfWork->scheduledInserts() !== []
            || $this->unitOfWork->scheduledUpdates() !== []
            || $this->unitOfWork->scheduledDeletes() !== []
            || $this->unitOfWork->scheduledForceDeletes() !== []
            || $this->unitOfWork->scheduledRestores() !== []
        ) {
            throw new LogicException('Bulk write cannot run while UnitOfWork has scheduled operations. Call flush() first.');
        }
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

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     */
    private function uniqueValues(array $values): array
    {
        $result = [];
        $seen   = [];

        foreach ($values as $value) {
            $key = $this->valueKey($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[]   = $value;
        }

        return $result;
    }

    private function valueKey(mixed $value): string
    {
        if ($value instanceof UuidInterface) {
            return 'uuid:' . $value->toString();
        }

        if (is_object($value) && method_exists($value, 'toString')) {
            return 'object:' . $value->toString();
        }

        if (is_int($value)) {
            return 'int:' . (string) $value;
        }

        if (is_scalar($value) || $value === null) {
            return 'scalar:' . (string) $value;
        }

        throw new InvalidArgumentException('Bulk lookup values must be scalar or stringable identifiers.');
    }
}
