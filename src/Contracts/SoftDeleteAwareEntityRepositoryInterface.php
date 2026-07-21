<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Contracts;

use Ramsey\Uuid\UuidInterface;

/**
 * Репозиторий, который умеет читать сущности без применения soft-delete scope.
 *
 * @template TEntity of EntityInterface
 * @extends EntityRepositoryInterface<TEntity>
 */
interface SoftDeleteAwareEntityRepositoryInterface extends EntityRepositoryInterface
{
    /**
     * @param int|UuidInterface|array<string, int|string|UuidInterface|null>|IdentifierInterface $id
     * @return TEntity|null
     */
    public function findWithDeleted(int|UuidInterface|array|IdentifierInterface $id): ?EntityInterface;

    /**
     * @param int|UuidInterface|array<string, int|string|UuidInterface|null>|IdentifierInterface $id
     */
    public function existsWithDeleted(int|UuidInterface|array|IdentifierInterface $id): bool;
}
