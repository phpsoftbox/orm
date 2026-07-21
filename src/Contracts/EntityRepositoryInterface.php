<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Orm\Collection\EntityCollection;
use Ramsey\Uuid\UuidInterface;

/**
 * Репозиторий, который умеет загружать сущности по идентификатору.
 *
 * @template TEntity of EntityInterface
 */
interface EntityRepositoryInterface extends RepositoryInterface
{
    /**
     * Находит сущность по идентификатору.
     *
     * Идентификатор задаётся "с заделом" под composite PK:
     * - int|UuidInterface (single PK)
     * - array (например: ['id' => 10])
     * - IdentifierInterface
     *
     * @param int|UuidInterface|array<string, int|string|UuidInterface|null>|IdentifierInterface $id
     * @return TEntity|null
     */
    public function find(int|UuidInterface|array|IdentifierInterface $id): ?EntityInterface;

    /**
     * Проверяет, существует ли запись в БД.
     *
     * @param int|UuidInterface|array<string, int|string|UuidInterface|null>|IdentifierInterface $id
     */
    public function exists(int|UuidInterface|array|IdentifierInterface $id): bool;

    /**
     * Возвращает все сущности.
     *
     * @return EntityCollection<TEntity>
     */
    public function all(): EntityCollection;
}
