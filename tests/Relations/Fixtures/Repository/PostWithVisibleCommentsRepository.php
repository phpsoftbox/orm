<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithVisibleComments;

/**
 * @extends AbstractEntityRepository<PostWithVisibleComments>
 */
final class PostWithVisibleCommentsRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return PostWithVisibleComments::class;
    }

    protected function table(): string
    {
        return 'posts_comments';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new PostWithVisibleComments(
            id: (int) $row['id'],
            title: (string) $row['title'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var PostWithVisibleComments $entity */
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
