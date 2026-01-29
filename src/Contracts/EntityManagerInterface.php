<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\QueryBuilder\SelectQueryBuilder;
use PhpSoftBox\Orm\Metadata\MetadataProviderInterface;
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
     * Находит сущность по идентификатору.
     *
     * Если используется AdvancedUnitOfWork, метод сперва проверяет IdentityMap (1st-level cache).
     *
     * @param class-string $entityClass
     * @param int|string|UuidInterface $id
     */
    public function find(string $entityClass, int|string|UuidInterface $id): ?EntityInterface;

    /**
     * Возвращает query builder для чтения данных сущности.
     *
     * По умолчанию применяет "глобальные" условия (behaviors) для чтения,
     * например SoftDelete (скрывает удалённые записи).
     *
     * @param class-string $entityClass
     */
    public function queryFor(string $entityClass, bool $withDeleted = false): SelectQueryBuilder;

    /**
     * Планирует физическое удаление сущности (hard delete), игнорируя soft delete behavior.
     */
    public function forceRemove(EntityInterface $entity): void;

    /**
     * Подгружает связи (relations) в сущность или список сущностей.
     *
     * На текущем этапе поддерживается только ManyToOne.
     *
     * @param EntityInterface|iterable<EntityInterface> $entities
     * @param string|list<string> $relations
     */
    public function load(EntityInterface|iterable $entities, string|array $relations): void;

    /**
     * Возвращает провайдер метаданных (read-only API).
     *
     * Нужен для интеграции/инструментов и тестов без использования Reflection.
     */
    public function metadataProvider(): MetadataProviderInterface;
}
