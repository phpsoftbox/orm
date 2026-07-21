<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithComments;

/**
 * @extends AbstractEntityRepository<PostWithComments>
 */
final class PostWithCommentsRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return PostWithComments::class;
    }

    protected function table(): string
    {
        return 'posts_comments';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new PostWithComments(
            id: (int) $row['id'],
            title: (string) $row['title'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var PostWithComments $entity */
        return [
            'id'    => $entity->id,
            'title' => $entity->title,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
    }

    protected function doRemove(EntityInterface $entity): void
    {
    }
}
