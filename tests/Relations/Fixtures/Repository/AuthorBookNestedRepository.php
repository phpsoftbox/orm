<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\AuthorBookNested;

/**
 * @extends AbstractEntityRepository<AuthorBookNested>
 */
final class AuthorBookNestedRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'author_books_nested';
    }

    protected function entityClass(): string
    {
        return AuthorBookNested::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new AuthorBookNested(
            id: (int) $row['id'],
            authorId: (int) $row['author_id'],
            title: (string) $row['title'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var AuthorBookNested $entity */
        return [
            'id'        => $entity->id,
            'author_id' => $entity->authorId,
            'title'     => $entity->title,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('AuthorBookNestedRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('AuthorBookNestedRepository fixture is read-only.');
    }
}
