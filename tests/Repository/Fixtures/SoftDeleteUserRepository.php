<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Repository\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Repository\Fixtures\SoftDeleteUser;

/**
 * @extends AbstractEntityRepository<SoftDeleteUser>
 */
final class SoftDeleteUserRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return SoftDeleteUser::class;
    }

    protected function table(): string
    {
        return 'sd_users';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new SoftDeleteUser(
            id: (int) $row['id'],
            name: (string) $row['name'],
            deletedDatetime: $row['deleted_datetime'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var SoftDeleteUser $entity */
        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'deleted_datetime' => $entity->deletedDatetime,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        // не нужно для этого теста
    }

    protected function doRemove(EntityInterface $entity): void
    {
        // не нужно для этого теста
    }
}
