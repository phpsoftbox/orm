<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostForThrough;

/**
 * @extends AbstractEntityRepository<PostForThrough>
 */
final class PostForThroughRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'posts_hmt';
    }

    protected function entityClass(): string
    {
        return PostForThrough::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new PostForThrough(
            id: (int) $row['id'],
            author_id: (int) $row['author_id'],
            title: (string) $row['title'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var PostForThrough $entity */
        return [
            'id' => $entity->id,
            'author_id' => $entity->author_id,
            'title' => $entity->title,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('PostForThroughRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('PostForThroughRepository fixture is read-only.');
    }
}
