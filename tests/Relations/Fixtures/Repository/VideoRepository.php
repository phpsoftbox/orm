<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Video;

/**
 * @extends AbstractEntityRepository<Video>
 */
final class VideoRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return Video::class;
    }

    protected function table(): string
    {
        return 'videos';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new Video(
            id: (int) $row['id'],
            title: (string) $row['title'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var Video $entity */
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
