<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Repository;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Orm\Contracts\RepositoryInterface;

/**
 * @template TEntity of EntityInterface
 */
abstract class AbstractRepository implements RepositoryInterface
{
    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly ?EntityManagerInterface $em = null,
    ) {
    }

    /**
     * Возвращает данные сущности в виде массива (для UnitOfWork/dirty-checking).
     *
     * @param TEntity $entity
     * @return array<string, mixed>
     */
    final public function data(EntityInterface $entity): array
    {
        return $this->extract($entity);
    }

    public function persist(EntityInterface $entity): void
    {
        $this->doPersist($entity);
    }

    public function remove(EntityInterface $entity): void
    {
        $this->doRemove($entity);
    }

    /**
     * @param TEntity $entity
     * @return array<string, mixed>
     */
    abstract protected function extract(EntityInterface $entity): array;

    /**
     * @param TEntity $entity
     */
    abstract protected function doPersist(EntityInterface $entity): void;

    /**
     * @param TEntity $entity
     */
    abstract protected function doRemove(EntityInterface $entity): void;
}
