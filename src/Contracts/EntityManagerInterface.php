<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\Bulk\EntityBulkWriter;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
use PhpSoftBox\Orm\QueryBuilder\OrmSelectQueryBuilder;
use PhpSoftBox\Orm\Relation\PivotRelationManager;
use PhpSoftBox\Orm\Relation\Scope\RelationScopeResolverInterface;
use Ramsey\Uuid\UuidInterface;

interface EntityManagerInterface
{
    /**
     * Возвращает DBAL-подключение, с которым работает ORM.
     */
    public function connection(): ConnectionInterface;

    /**
     * Возвращает UnitOfWork, который отслеживает состояние сущностей.
     */
    public function unitOfWork(): UnitOfWorkInterface;

    /**
     * Ставит сущность в очередь на сохранение.
     */
    public function persist(EntityInterface $entity): void;

    /**
     * Ставит сущность в очередь на удаление.
     */
    public function remove(EntityInterface $entity): void;

    /**
     * Применяет все накопленные операции (persist/remove).
     */
    public function flush(): void;

    /**
     * Возвращает репозиторий для указанного класса сущности.
     *
     * @param class-string $entityClass
     */
    public function repository(string $entityClass): RepositoryInterface;

    /**
     * Возвращает репозиторий с поддержкой batch-гидрации.
     *
     * @param class-string $entityClass
     */
    public function bulkRepository(string $entityClass): BulkEntityRepositoryInterface;

    /**
     * Возвращает set-based bulk writer для массовых операций без загрузки сущностей.
     *
     * @param class-string $entityClass
     */
    public function bulk(string $entityClass): EntityBulkWriter;

    /**
     * Находит сущность по идентификатору.
     *
     * Метод сперва проверяет EntityHeap текущего UnitOfWork (1st-level cache).
     *
     * @param class-string $entityClass
     */
    public function find(string $entityClass, int|string|UuidInterface $id): ?EntityInterface;

    /**
     * Находит сущность по идентификатору без применения soft-delete scope.
     *
     * Как и find(), метод использует IdentityMap текущего UnitOfWork и всегда
     * возвращает канонический managed-экземпляр сущности.
     *
     * @param class-string $entityClass
     */
    public function findWithDeleted(string $entityClass, int|string|UuidInterface $id): ?EntityInterface;

    /**
     * Возвращает query builder для чтения данных сущности.
     *
     * По умолчанию применяет "глобальные" условия (behaviors) для чтения,
     * например SoftDelete (скрывает удалённые записи).
     *
     * @param class-string $entityClass
     */
    public function queryFor(string $entityClass, bool $withDeleted = false): OrmSelectQueryBuilder;

    /**
     * Планирует физическое удаление сущности (hard delete), игнорируя soft delete behavior.
     */
    public function forceRemove(EntityInterface $entity): void;

    /**
     * Планирует восстановление soft-deleted сущности.
     */
    public function restore(EntityInterface $entity): void;

    /**
     * Подгружает связи (relations) в сущность или список сущностей.
     *
     * @param EntityInterface|iterable<EntityInterface> $entities
     * @param string|list<string> $relations
     */
    public function load(EntityInterface|iterable $entities, string|array $relations): void;

    /**
     * Загружает только отсутствующие relations, не перезаписывая уже загруженные значения.
     *
     * @param EntityInterface|iterable<EntityInterface> $entities
     * @param string|list<string> $relations
     */
    public function loadMissing(EntityInterface|iterable $entities, string|array $relations): void;

    /**
     * Принудительно перезагружает указанные relations.
     *
     * @param EntityInterface|iterable<EntityInterface> $entities
     * @param string|list<string> $relations
     */
    public function reload(EntityInterface|iterable $entities, string|array $relations): void;

    /**
     * Возвращает провайдер метаданных (read-only API).
     *
     * Нужен для интеграции/инструментов и тестов без использования Reflection.
     */
    public function metadataProvider(): MetadataProviderInterface;

    /**
     * Возвращает resolver relation scope-классов.
     */
    public function relationScopeResolver(): RelationScopeResolverInterface;

    /**
     * Возвращает канонические managed-экземпляры для результатов гидрации.
     *
     * @param iterable<EntityInterface> $entities
     */
    public function manageHydratedEntities(iterable $entities): EntityCollection;

    /**
     * Перезагружает состояние сущности из БД в текущий объект.
     *
     * Используйте, если:
     * - БД меняет данные через DEFAULT/trigger/generated columns,
     * - кто-то изменил запись вне ORM,
     * - нужно сбросить локальные изменения.
     *
     * Важно: метод работает только для сущностей с заданным идентификатором (id() != null).
     */
    public function refresh(EntityInterface $entity): void;

    /**
     * Возвращает менеджер для управления many-to-many связью через pivot-таблицу.
     *
     * Пример:
     *   $em->pivot($user, 'roles')->attach(10);
     *
     * @param non-empty-string $relationProperty
     */
    public function pivot(EntityInterface $owner, string $relationProperty): PivotRelationManager;
}
