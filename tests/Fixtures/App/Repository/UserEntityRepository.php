<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Fixtures\App\Repository;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\RepositoryInterface;

/**
 * Репозиторий-фикстура для проверки defaultRepositoryNamespace.
 */
final readonly class UserEntityRepository implements RepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {
    }

    public function persist(EntityInterface $entity): void
    {
    }

    public function remove(EntityInterface $entity): void
    {
    }
}

