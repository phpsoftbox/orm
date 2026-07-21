<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithNestedComments;

/**
 * @extends AbstractEntityRepository<PostWithNestedComments>
 */
final class PostWithNestedCommentsRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'posts_nested_comments';
    }

    protected function entityClass(): string
    {
        return PostWithNestedComments::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new PostWithNestedComments(
            id: (int) $row['id'],
            title: (string) $row['title'],
            comments: new EntityCollection([]),
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var PostWithNestedComments $entity */
        return [
            'id'    => $entity->id,
            'title' => $entity->title,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('PostWithNestedCommentsRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('PostWithNestedCommentsRepository fixture is read-only.');
    }
}
