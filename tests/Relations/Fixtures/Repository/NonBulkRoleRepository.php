<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\RepositoryInterface;

/**
 * Репозиторий-фикстура без BulkEntityRepositoryInterface.
 * Используется для проверки fallback'а на GenericEntityRepository при загрузке связей.
 */
final class NonBulkRoleRepository implements RepositoryInterface
{
    public function persist(EntityInterface $entity): void
    {
        throw new LogicException('NonBulkRoleRepository is read-only.');
    }

    public function remove(EntityInterface $entity): void
    {
        throw new LogicException('NonBulkRoleRepository is read-only.');
    }
}
