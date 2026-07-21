<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\FixturesPivot\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\Role;

/**
 * @extends AbstractEntityRepository<Role>
 */
final class RoleRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'roles_pivot_rel';
    }

    protected function entityClass(): string
    {
        return Role::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        $role = new Role();

        $role->id   = (int) $row['id'];
        $role->name = (string) $row['name'];

        return $role;
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
