<?php

declare(strict_types=1);

namespace PhpSoftBox\Orm\Tests\Relations\Fixtures\Repository;

use LogicException;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Repository\AbstractEntityRepository;
use PhpSoftBox\Orm\Tests\Relations\Fixtures\AuthorNested;

/**
 * @extends AbstractEntityRepository<AuthorNested>
 */
final class AuthorNestedRepository extends AbstractEntityRepository
{
    protected function table(): string
    {
        return 'authors_nested';
    }

    protected function entityClass(): string
    {
        return AuthorNested::class;
    }

    protected function hydrate(array $row): EntityInterface
    {
        return new AuthorNested(
            id: (int) $row['id'],
            name: (string) $row['name'],
        );
    }

    protected function extract(EntityInterface $entity): array
    {
        /** @var AuthorNested $entity */
        return [
            'id' => $entity->id,
            'name' => $entity->name,
        ];
    }

    protected function doPersist(EntityInterface $entity): void
    {
        throw new LogicException('AuthorNestedRepository fixture is read-only.');
    }

    protected function doRemove(EntityInterface $entity): void
    {
        throw new LogicException('AuthorNestedRepository fixture is read-only.');
    }
}
