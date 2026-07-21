<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithNestedAuthor;

/**
 * @extends AbstractEntityRepository<PostWithNestedAuthor>
 */
final class PostWithNestedAuthorRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return PostWithNestedAuthor::class;
    }

    protected function table(): string
    {
        return 'posts_nested';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new PostWithNestedAuthor(
            id: (int) $row['id'],
            authorId: (int) $row['author_id'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var PostWithNestedAuthor $entity */
        return [
            'id'        => $entity->id,
            'author_id' => $entity->authorId,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
    }

    protected function doRemove(EntityInterface $entity): void
    {
    }
}
