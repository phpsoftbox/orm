<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\CommentWithAuthor;

/**
 * @extends AbstractEntityRepository<CommentWithAuthor>
 */
final class CommentWithAuthorRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'comments_nested';
    }

    protected function entityClass(): string
    {
        return CommentWithAuthor::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new CommentWithAuthor(
            id: (int) $row['id'],
            postId: (int) $row['post_id'],
            authorId: (int) $row['author_id'],
            body: (string) $row['body'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var CommentWithAuthor $entity */
        return [
            'id'        => $entity->id,
            'post_id'   => $entity->postId,
            'author_id' => $entity->authorId,
            'body'      => $entity->body,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('CommentWithAuthorRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('CommentWithAuthorRepository fixture is read-only.');
    }
}
