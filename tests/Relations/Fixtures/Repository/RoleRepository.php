<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Role;

/**
 * @extends AbstractEntityRepository<Role>
 */
final class RoleRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'roles_rel';
    }

    protected function entityClass(): string
    {
        return Role::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new Role(
            id: (int) $row['id'],
            name: (string) $row['name'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var Role $entity */
        return [
            'id'   => $entity->id,
            'name' => $entity->name,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('RoleRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('RoleRepository fixture is read-only.');
    }
}
