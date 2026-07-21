<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\FixturesPivot\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\FixturesPivot\User;

/**
 * @extends AbstractEntityRepository<User>
 */
final class UserRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'users_pivot_rel';
    }

    protected function entityClass(): string
    {
        return User::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        $user = new User();

        $user->id   = (int) $row['id'];
        $user->name = (string) $row['name'];

        return $user;
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var User $entity */
        return [
            'id'   => $entity->id,
            'name' => $entity->name,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('UserRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('UserRepository fixture is read-only.');
    }
}
