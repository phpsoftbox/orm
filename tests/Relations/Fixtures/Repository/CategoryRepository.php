<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\Category;

/**
 * @extends AbstractEntityRepository<Category>
 */
final class CategoryRepository extends AbstractEntityRepository
{
    protected function entityClass(): string
    {
        return Category::class;
    }

    protected function table(): string
    {
        return 'categories';
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new Category(
            id: (int) $row['id'],
            parentId: isset($row['parent_id']) ? (int) $row['parent_id'] : null,
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var Category $entity */
        return [
            'id' => $entity->id,
            'parent_id' => $entity->parentId,
        ];
    }

    protected function doPersist(EntityInterface $entity): void {}

    protected function doRemove(EntityInterface $entity): void {}
}
