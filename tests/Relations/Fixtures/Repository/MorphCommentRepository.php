<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\MorphComment;

/**
 * @extends AbstractEntityRepository<MorphComment>
 */
final class MorphCommentRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return MorphComment::class;
    }

    protected function table(): string
    {
        return 'morph_comments';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new MorphComment(
            id: (int) $row['id'],
            commentableType: (string) $row['commentable_type'],
            commentableId: (int) $row['commentable_id'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var MorphComment $entity */
        return [
            'id' => $entity->id,
            'commentable_type' => $entity->commentableType,
            'commentable_id' => $entity->commentableId,
        ];
    }

    protected function doPersist(EntityInterface $entity): void {}

    protected function doRemove(EntityInterface $entity): void {}
}
