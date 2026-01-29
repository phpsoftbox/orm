<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\UserWithRoles;

/**
 * @extends AbstractEntityRepository<UserWithRoles>
 */
final class UserWithRolesRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'users_roles_rel';
    }

    protected function entityClass(): string
    {
        return UserWithRoles::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new UserWithRoles(
            id: (int) $row['id'],
            name: (string) $row['name'],
            roles: new EntityCollection([]),
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var UserWithRoles $entity */
        return [
            'id' => $entity->id,
            'name' => $entity->name,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('UserWithRolesRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('UserWithRolesRepository fixture is read-only.');
    }
}
