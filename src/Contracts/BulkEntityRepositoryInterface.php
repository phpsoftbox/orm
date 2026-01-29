<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use PhpSoftBox\Orm\Collection\EntityCollection;

/**
 * Расширение EntityRepositoryInterface для репозиториев, которые поддерживают batch-загрузку.
 *
 * Используется EntityManager'ом для eager loading связей, чтобы избежать N+1.
 *
 * @template TEntity of EntityInterface
 * @extends EntityRepositoryInterface<TEntity>
 */
interface BulkEntityRepositoryInterface extends EntityRepositoryInterface
{
    /**
     * Батч-загрузка по списку идентификаторов.
     *
     * @param list<int|string> $ids
     * @return EntityCollection<TEntity>
     */
    public function findManyByColumn(array $ids, string $column = 'id', bool $withDeleted = false): EntityCollection;

    /**
     * Батч-гидрация: преобразует список строк из БД в коллекцию сущностей.
     *
     * @param list<array<string, mixed>> $rows
     * @return EntityCollection<TEntity>
     */
    public function hydrateManyRows(array $rows): EntityCollection;
}
