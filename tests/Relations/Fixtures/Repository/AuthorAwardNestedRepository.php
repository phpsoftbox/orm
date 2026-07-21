<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\AuthorAwardNested;

/**
 * @extends AbstractEntityRepository<AuthorAwardNested>
 */
final class AuthorAwardNestedRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'author_awards_nested';
    }

    protected function entityClass(): string
    {
        return AuthorAwardNested::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new AuthorAwardNested(
            id: (int) $row['id'],
            authorId: (int) $row['author_id'],
            name: (string) $row['name'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var AuthorAwardNested $entity */
        return [
            'id'        => $entity->id,
            'author_id' => $entity->authorId,
            'name'      => $entity->name,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('AuthorAwardNestedRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('AuthorAwardNestedRepository fixture is read-only.');
    }
}
