<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostBelongsTo;

/**
 * @extends AbstractEntityRepository<PostBelongsTo>
 */
final class PostBelongsToRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'posts_rel';
    }

    protected function entityClass(): string
    {
        return PostBelongsTo::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new PostBelongsTo(
            id: (int) $row['id'],
            authorId: (int) $row['author_id'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var PostBelongsTo $entity */
        return [
            'id' => $entity->id,
            'author_id' => $entity->authorId,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('PostBelongsToRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('PostBelongsToRepository fixture is read-only.');
    }
}
