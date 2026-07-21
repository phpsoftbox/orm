<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Author;

/**
 * @extends AbstractEntityRepository<Author>
 */
final class AuthorRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return Author::class;
    }

    protected function table(): string
    {
        return 'authors';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new Author(
            id: (int) $row['id'],
            name: (string) $row['name'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var Author $entity */
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
