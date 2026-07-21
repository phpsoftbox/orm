<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\UserWithProfile;

/**
 * @extends AbstractEntityRepository<UserWithProfile>
 */
final class UserWithProfileRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return UserWithProfile::class;
    }

    protected function table(): string
    {
        return 'users_profile';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new UserWithProfile(
            id: (int) $row['id'],
            name: (string) $row['name'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var UserWithProfile $entity */
        return [
            'id'   => $entity->id,
            'name' => $entity->name,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
    }

    protected function doRemove(EntityInterface $entity): void
    {
    }
}
