<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Comment;

/**
 * @extends AbstractEntityRepository<Comment>
 */
final class CommentRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return Comment::class;
    }

    protected function table(): string
    {
        return 'comments';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new Comment(
            id: (int) $row['id'],
            postId: (int) $row['post_id'],
            body: (string) $row['body'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var Comment $entity */
        return [
            'id' => $entity->id,
            'post_id' => $entity->postId,
            'body' => $entity->body,
        ];
    }

    protected function doPersist(EntityInterface $entity): void {}

    protected function doRemove(EntityInterface $entity): void {}
}

