<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Fixtures\User;
use Ramsey\Uuid\Uuid;

/**
 * @extends AbstractEntityRepository<User>
 */
final class UserRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return User::class;
    }

    protected function table(): string
    {
        return 'users';
    }

    protected function hydrate(array $row): EntityInterface
    {
        /** @var string $id */
        $id = $row['id'];

        /** @var string $name */
        $name = $row['name'];

        return new User(Uuid::fromString($id), $name);
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var User $entity */
        return [
            'id'   => $entity->id->toString(),
            'name' => $entity->name,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        // В тестовой фикстуре не реализуем.
    }

    protected function doRemove(EntityInterface $entity): void
    {
        // В тестовой фикстуре не реализуем.
    }
}
