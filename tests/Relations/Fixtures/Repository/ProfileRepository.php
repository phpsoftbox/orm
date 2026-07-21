<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Profile;

/**
 * @extends AbstractEntityRepository<Profile>
 */
final class ProfileRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return Profile::class;
    }

    protected function table(): string
    {
        return 'profiles';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new Profile(
            id: (int) $row['id'],
            userId: (int) $row['user_id'],
            bio: (string) $row['bio'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var Profile $entity */
        return [
            'id'      => $entity->id,
            'user_id' => $entity->userId,
            'bio'     => $entity->bio,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
    }

    protected function doRemove(EntityInterface $entity): void
    {
    }
}
