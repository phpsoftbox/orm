<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Repository;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\BulkEntityRepositoryInterface;
use PhpSoftBox\Orm\Contracts\IdentifierInterface;
use PhpSoftBox\Orm\Exception\CompositePrimaryKeyNotSupportedException;
use PhpSoftBox\Orm\Identifier\SingleIdentifier;
use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;
use Ramsey\Uuid\UuidInterface;

use function array_map;
use function array_values;
use function count;

/**
 * Базовый репозиторий для сущностей.
 *
 * @template TEntity of EntityInterface
 * @extends AbstractRepository<TEntity>
 */
abstract class AbstractEntityRepository extends AbstractRepository implements BulkEntityRepositoryInterface
{
    /**
     * Имя таблицы (без префикса). Префикс добавляется через ConnectionInterface::table().
     */
    abstract protected function table(): string;

    /**
     * Название колонки первичного ключа.
     *
     * По умолчанию: "id".
     */
    protected function pkColumn(): string
    {
        return 'id';
    }

    /**
     * Колонки первичного ключа.
     *
     * Сейчас ORM по умолчанию работает с single-PK, но метод сделан списком
     * для расширения на composite PK в будущем.
     *
     * @return list<string>
     */
    protected function pkColumns(): array
    {
        return [$this->pkColumn()];
    }

    /**
     * Маппинг строки из БД в сущность.
     *
     * @param array<string, mixed> $row
     * @return TEntity
     */
    abstract protected function hydrate(array $row): EntityInterface;

    /**
     * Находит сущность по идентификатору.
     *
     * Поддерживаемые формы (с заделом под composite PK):
     * - int / UUID (single PK)
     * - ассоциативный массив [pkColumn => value]
     * - объект IdentifierInterface
     *
     * Важно: сейчас репозиторий поддерживает только single primary key.
     *
     * @param int|UuidInterface|array<string, int|string|UuidInterface|null>|IdentifierInterface $id
     * @return TEntity|null
     */
    public function find(int|UuidInterface|array|IdentifierInterface $id): ?EntityInterface
    {
        $where = $this->normalizeIdentifier($id);

        $row = $this->query()
            ->where($where['sql'], $where['params'])
            ->fetchOne();

        $entity = $row === null ? null : $this->hydrate($row);

        if ($entity !== null && $this->withRelations !== [] && $this->em !== null) {
            $this->em->load($entity, $this->withRelations);
        }

        return $entity;
    }

    /**
     * @return EntityCollection<TEntity>
     */
    public function all(): EntityCollection
    {
        $rows = $this->query()->fetchAll();

        $entities = array_map(fn (array $row) => $this->hydrate($row), $rows);

        if ($entities !== [] && $this->withRelations !== [] && $this->em !== null) {
            $this->em->load($entities, $this->withRelations);
        }

        return new EntityCollection($entities);
    }

    /**
     * Проверяет, существует ли запись в БД.
     *
     * @param int|UuidInterface|array<string, int|string|UuidInterface|null>|IdentifierInterface $id
     */
    public function exists(int|UuidInterface|array|IdentifierInterface $id): bool
    {
        $where = $this->normalizeIdentifier($id);

        $row = $this->query()
            ->select(['1 AS exists_flag'])
            ->where($where['sql'], $where['params'])
            ->limit(1)
            ->fetchOne();

        return $row !== null;
    }

    /**
     * Включает eager loading связей для результатов репозитория.
     *
     * Пример:
     *   $repo->with(['author'])->all();
     *
     * @param list<string> $relations
     */
    public function with(array $relations): static
    {
        $clone = clone $this;
        $clone->withRelations = $relations;
        return $clone;
    }

    /**
     * Батч-загрузка по списку идентификаторов.
     *
     * Нужен для prefetch (eager loading), чтобы избежать N+1.
     *
     * @param list<int|string> $ids
     */
    public function findManyByColumn(array $ids, string $column = 'id', bool $withDeleted = false): EntityCollection
    {
        if ($ids === []) {
            return new EntityCollection([]);
        }

        $rows = $this->query(withDeleted: $withDeleted)
            ->whereIn($column, $ids)
            ->fetchAll();

        return new EntityCollection(array_map(fn (array $row) => $this->hydrate($row), $rows));
    }

    /**
     * Гидрирует список строк из БД в коллекцию сущностей.
     *
     * Нужен для сценариев, когда выборка строится вне репозитория (например, через JOIN для связей),
     * а ответственность за создание объектов сущностей остаётся в репозитории.
     *
     * @param list<array<string, mixed>> $rows
     * @return EntityCollection<TEntity>
     */
    final public function hydrateManyRows(array $rows): EntityCollection
    {
        return new EntityCollection(array_map(fn (array $row) => $this->hydrate($row), $rows));
    }

    /**
     * Нормализует идентификатор к формату, понятному QueryBuilder.
     *
     * Сейчас поддерживается только single primary key.
     *
     * @param int|UuidInterface|array<string, int|string|UuidInterface|null>|IdentifierInterface $id
     * @return array{sql: string, params: array<string, int|string|null>}
     */
    final protected function normalizeIdentifier(int|UuidInterface|array|IdentifierInterface $id): array
    {
        $pkColumns = $this->pkColumns();

        if (count($pkColumns) !== 1) {
            throw new CompositePrimaryKeyNotSupportedException('Composite primary keys are not supported yet.');
        }

        $pk = $pkColumns[0];

        $identifier = match (true) {
            $id instanceof IdentifierInterface => $id,
            is_array($id) => $this->identifierFromArray($id),
            default => new SingleIdentifier($pk, $id),
        };

        $values = $identifier->values();

        if (count($values) !== 1) {
            throw new CompositePrimaryKeyNotSupportedException('Composite identifiers are not supported yet.');
        }

        if (!array_key_exists($pk, $values)) {
            throw new CompositePrimaryKeyNotSupportedException('Identifier does not contain expected primary key column: ' . $pk);
        }

        $value = $values[$pk];

        if ($value instanceof UuidInterface) {
            $value = $value->toString();
        }

        // Parameter name intentionally does NOT equal $pk (it may contain dots or reserved words in future).
        $param = '__orm_pk';

        return [
            'sql' => $pk . ' = :' . $param,
            'params' => [$param => $value],
        ];
    }

    /**
     * @param array<string, int|string|UuidInterface|null> $id
     */
    private function identifierFromArray(array $id): IdentifierInterface
    {
        $pkColumns = $this->pkColumns();

        if (count($pkColumns) !== 1) {
            throw new CompositePrimaryKeyNotSupportedException('Composite primary keys are not supported yet.');
        }

        $pk = $pkColumns[0];

        if (count($id) !== 1) {
            throw new CompositePrimaryKeyNotSupportedException('Composite identifiers are not supported yet.');
        }

        $values = array_values($id);

        if (array_key_exists($pk, $id)) {
            return new SingleIdentifier($pk, $id[$pk]);
        }

        // допускаем удобный формат: ['id' => 123] (правильный ключ),
        // но если передали просто [0 => 123], трактуем как значение pk.
        if (count($values) === 1 && array_key_exists(0, $id)) {
            return new SingleIdentifier($pk, $values[0]);
        }

        throw new CompositePrimaryKeyNotSupportedException('Identifier array must contain only primary key column: ' . $pk);
    }

    /**
     * Класс сущности, с которой работает репозиторий.
     *
     * Нужен, чтобы репозиторий мог получать query builder через EntityManager::queryFor()
     * и автоматически применять read-behaviors (например soft delete).
     *
     * @return class-string<TEntity>
     */
    abstract protected function entityClass(): string;

    /**
     * Возвращает билдер для чтения данных сущности.
     */
    protected function query(bool $withDeleted = false): SelectQueryBuilder
    {
        if ($this->em !== null) {
            return $this->em->queryFor($this->entityClass(), $withDeleted);
        }

        // fallback (без read-behaviors)
        return $this->connection
            ->query()
            ->select()
            ->from($this->table());
    }

    /**
     * Список связей для eager loading.
     *
     * @var list<string>
     */
    private array $withRelations = [];

    // NOTE: insert/update логика будет добавлена после того, как мы договоримся об API UnitOfWork/Mapper.
}
