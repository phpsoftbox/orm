<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Collection\EntityCollection;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\PostWithMorphComments;

/**
 * @extends AbstractEntityRepository<PostWithMorphComments>
 */
final class PostWithMorphCommentsRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return PostWithMorphComments::class;
    }

    protected function table(): string
    {
        return 'posts_morph';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new PostWithMorphComments(
            id: (int) $row['id'],
            title: (string) $row['title'],
            comments: new EntityCollection([]),
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var PostWithMorphComments $entity */
        return [
            'id' => $entity->id,
            'title' => $entity->title,
        ];
    }

    protected function doPersist(EntityInterface $entity): void {}

    protected function doRemove(EntityInterface $entity): void {}
}
